<?php

/**
 * Created by PhpStorm.
 * User: Lazarev Aleksey
 * Date: 28.08.16
 * Time: 19:31
 */
class Requester
{
    private $cookies = null;
    private $ch = null;

    public function __construct()
    {
        $this->cookies = tempnam(dirname(__FILE__), "cookies");

        try {
            $this->ch = curl_init();
            if ($this->ch === false) {
                throw new Exception('failed to initialize');
            }

            $this->ch = curl_init();
            curl_setopt($this->ch, CURLOPT_URL, 'https://yandex.ru');
            curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
            //curl_setopt($this->ch, CURLOPT_VERBOSE, true);
            curl_setopt($this->ch, CURLOPT_HEADER, true);
            curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookies);
            curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($this->ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($this->ch, CURLOPT_HEADER, 0);

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    public function http_response($url, $data = [])
    {
        if ($this->urlValidate($url)) {
            try {
                curl_setopt($this->ch, CURLOPT_URL, $url);
                if (empty($data)) {
                    curl_setopt($this->ch, CURLOPT_POST, 0);
                } else {
                    curl_setopt($this->ch, CURLOPT_POST, 1);
                    curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }

                $response = curl_exec($this->ch);
                if (curl_errno($this->ch)) {
                    throw new Exception('Service unavailable... try again later...');
                }
                $statusCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
                $header_size = curl_getinfo($this->ch, CURLINFO_HEADER_SIZE);
                $body = substr($response, $header_size);

                if ($statusCode == 200) {
                    return $body;
                } else {
                    throw new Exception($this->getResponse($statusCode));
                }
            } catch (Exception $e) {
                echo $e->getLine();
                throw new Exception($e->getMessage());
            }
        } else {
            throw new Exception('url not valid');
        }

    }

    protected function getResponseCodes()
    {
        return [
            '400' => 'Bad request',
            '401' => 'Unauthorized',
            '403' => 'Forbidden',
            '404' => '404',
            '429' => 'Rate limit exceeded',
            '500' => 'Internal server error',
            '503' => 'Service unavailable',
        ];
    }

    protected function urlValidate($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL);
    }

    protected function getResponse($code)
    {
        return $this->getResponseCodes()[$code];
    }

}

class Yandex
{

    private $requester;
    private $auth = false;

    public function __construct(Requester $requester)
    {
        $this->requester = $requester;
    }

    public function login($login, $password)
    {
        if (!$this->auth) {
            if (empty($login) or !is_string($login)) {
                throw new Exception('login wrong format');
            }
            if (empty($password) or !is_string($password)) {
                throw new Exception('password wrong format');
            }
            $result =  $this->requester->http_response('https://passport.yandex.ru/passport?mode=auth&retpath=https://mail.yandex.ru', ['login' => $login, 'passwd' => $password]);

            if(strpos($result, '<a href="../lite">')){
                $this->auth = true;
                return true;
            }
        } else {
            throw new Exception('already auth');
        }
        return false;
    }

    public function getMessages()
    {
        if(!$this->checkAuth()){
            throw new Exception('not auth');
        }
        try {
            $body = $this->requester->http_response("https://mail.yandex.ru/lite/inbox");
            $messages = '/\/lite\/message\/\d+/';
            $array = [];

            preg_match($messages, $body, $out);
            if (!empty($out[0])) {
                $next = $out[0];
                while ($next != '') {
                    $message = $this->getMessage('https://mail.yandex.ru' . $next);
                    $array[] = [
                        'subject' => $message['subject'],
                        'email' => $message['email'],
                        'from' => $message['from'],
                        'date' => $message['date'],
                    ];
                    $next = $message['next'];
                }
            }
            return $array;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

    }
    private function checkAuth(){
        if(!$this->auth){
            return false;
        }else{
            return true;
        }
    }

    private function getMessage($url)
    {
        if(!$this->checkAuth()){
            throw new Exception('not auth');
        }
        $body = $this->requester->http_response($url);
        preg_match('/<span class="b-message-head__subject-text">(.*)?<\/span>/U', $body, $subject);
        preg_match_all('/class="b-message-head__email">(.*)?<\/a>/U', $body, $email);
        preg_match('/<span class="b-message-head__person">(.*)?<\/span>/U', $body, $from);
        preg_match('/<span class="b-message-head__date">(.*)?<\/span>/U', $body, $date);
        preg_match('/<span class="b-message-pager__active"><a href="\/lite\/message\/(.*)?\/" class="b-message-pager__next">/U', $body, $next);
        if(!empty($next[1])){
            $next = '/lite/message/'.$next[1];
        }else{
            $next = '';
        }

        return [
            'subject' => isset($subject[1]) ? $subject[1] : '',
            'email' => isset($email[1][1]) ? $email[1][1] : '',
            'from' => isset($from[1] )? $from[1] : '',
            'date' => isset($date[1]) ? $date[1] : '',
            'next' => $next,
        ];
    }
}

interface RenderInterface{
    public static function render(array $message);
}

class RenderJson implements RenderInterface{
    public static function render(array $message)
    {
        echo json_encode($message);
    }
}


$ya = new Yandex(new Requester());
$ya->login('login', 'password');
$messages = $ya->getMessages();
RenderJson::render($ya->getMessages());



