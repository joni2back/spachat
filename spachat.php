<?php

/**
 * SPA Chat - Simple PHP Ajax Chat
 *
 * @date    2013-05-31
 * @author  Jonas Sciangula Street <joni2back {at} gmail.com>
 */

namespace SPA_Common;

define('DB_USERNAME',       'root');
define('DB_PASSWORD',       'root');
define('DB_HOST',           'localhost');
define('DB_NAME',           'chatangular');
define('CHAT_HISTORY',      '70');
define('CHAT_ONLINE_RANGE', '1');
define('ADMIN_USERNAME_PREFIX', 'adm123_');

class SPA_MySQL_Database 
{
    private $_dbLink, $_queryResponse;
    public $lastResult;
    
    public function __construct()
    {
        $this->_connect();
    }
    
    private function _connect()
    {
        $this->_dbLink = mysql_connect(DB_HOST, DB_USERNAME, DB_PASSWORD);
        mysql_select_db(DB_NAME, $this->_dbLink);
    }
    
    public function query($query) 
    {
        $this->_queryResponse = mysql_query($query, $this->_dbLink);
        if ($this->_queryResponse) {
            return $this;
        }
    }
    
    public function getResults() 
    {
        $this->lastResult = array();
        if ($this->_queryResponse && !is_bool($this->_queryResponse)) {
            while ($response = mysql_fetch_object($this->_queryResponse)) {
                $this->lastResult[] = $response;
            }
            mysql_free_result($this->_queryResponse);
        }
        return $this->lastResult;
    }
    
    public function getOne()
    {
        $this->lastResult = null;
        if ($this->_queryResponse && !is_bool($this->_queryResponse)) {
            $this->lastResult = mysql_fetch_object($this->_queryResponse);
            mysql_free_result($this->_queryResponse);
        }
        return $this->lastResult;    
    }
    
    public function disconnect()
    {
        return mysql_close($this->_dbLink);
    }
}

abstract class Model
{
    public $db;
    
    public function __construct()
    {
        $this->db = new SPA_MySQL_Database;
    }
}

abstract class Controller
{
    private $_request, $_response, $_query, $_post, $_server, $_cookies;
    protected $_currentAction, $_defaultModel;
    
    const ACTION_POSTFIX = 'Action';
    const ACTION_DEFAULT = 'indexAction';
    
    public function __construct()
    {
        $this->_request  = &$_REQUEST;
        $this->_query    = &$_GET;
        $this->_post     = &$_POST;
        $this->_server   = &$_SERVER;
        $this->_cookies  = &$_COOKIE;
        $this->init();
    }
    
    public function init()
    {
        $this->dispatchActions();
        $this->render();
    }
    
    public function dispatchActions()
    {
        $action = $this->getQuery('action');
        if ($action && $action .= self::ACTION_POSTFIX) {
            if (method_exists($this, $action)) {
                $this->setResponse(
                    call_user_func(array($this, $action), array())
                );
            } else {
                $this->setHeader("HTTP/1.0 404 Not Found");
            }
        } else {
            $this->setResponse(
                call_user_func(array($this, self::ACTION_DEFAULT), array())
            );
        }
        return $this->_response;
    }
    
    public function render()
    {
        if ($this->_response) {
            if (is_scalar($this->_response)) {
                echo $this->_response;
            } else {
                throw new \Exception('Response content must be type scalar');
            }
            exit;
        }
    }
    
    public function indexAction()
    {
        return;
    }    
    
    public function setResponse($content)
    {
        $this->_response = $content;
    }
    
    public function setHeader($params)
    {
        if (! headers_sent()) {
            if (is_scalar($params)) {
                header($params);
            } else {
                foreach($params as $key => $value) {
                    header(sprintf('%s: %s', $key, $value));
                }
            }
        }
        return $this;
    }
    
    public function setModel($namespace)
    {
        $this->_defaultModel = $namespace;
        return $this;
    }
    
    public function setSession($key, $value)
    {
        $_SESSION[$key] = $value;
        return $this;
    }
    
    public function setCookie($key, $value, $seconds = 3600)
    {
        $this->_cookies[$key] = $value;
        if (! headers_sent()) {
            setcookie($key, $value, time() + $seconds);
            return $this;
        }
    }    
    
    public function getRequest($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_request[$param]) ? 
                $this->_request[$param] : $default;
        }
        return $this->_request;
    }
    
    public function getQuery($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_query[$param]) ? 
                $this->_query[$param] : $default;
        }
        return $this->_query;    
    }
    
    public function getPost($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_post[$param]) ? 
                $this->_post[$param] : $default;
        }
        return $this->_post;
    }
    
    public function getServer($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_server[$param]) ? 
                $this->_server[$param] : $default;
        }
        return $this->_server;
    }
    
    public function getSession($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_session[$param]) ? 
                $this->_session[$param] : $default;
        }
        return $this->_session;
    }
    
    public function getCookie($param = null, $default = null)
    {
        if ($param) {
            return isset($this->_cookies[$param]) ? 
                $this->_cookies[$param] : $default;
        }
        return $this->_cookies;
    }    
    
    public function getModel() 
    {
        if ($this->_defaultModel && class_exists($this->_defaultModel)) {
            return new $this->_defaultModel;
        }
    }
    
    public function sanitize($string, $quotes = ENT_QUOTES, $charset = 'utf-8') 
    {
        return htmlentities($string, $quotes, $charset);
    }
}

abstract class Helper
{

}


namespace SPA_Chat;
use SPA_Common;
class Model extends SPA_Common\Model
{
    
    public function getMessages($limit = CHAT_HISTORY, $reverse = true)
    {
        $response = $this->db->query("SELECT * FROM messages 
            ORDER BY date DESC LIMIT {$limit}");
        return $response->getResults();
    }
    
    public function addMessage($username, $message, $ip)
    {
        $username = addslashes($username);
        $message = addslashes($message);
        return (bool) $this->db->query("INSERT INTO messages 
            VALUES (NULL, '{$username}', '{$message}', '{$ip}', NOW())");
    }
    
    public function removeMessages()
    {
        return (bool) $this->db->query("TRUNCATE TABLE messages");
    }    
    
    public function removeOldMessages($limit = CHAT_HISTORY)
    {
        return false;
        return (bool) $this->db->query("DELETE FROM messages 
            WHERE id NOT IN (SELECT id FROM messages 
                ORDER BY date DESC LIMIT {$limit})");
    }
    
    public function getOnline($count = true, $timeRange = CHAT_ONLINE_RANGE)
    {
        if ($count) {
            $response = $this->db->query("SELECT count(*) as total FROM online");
            return $response->getOne();
        }
        return $this->db->query("SELECT ip FROM online")->getResults();
    }
    
    public function updateOnline($hash, $ip)
    {
        return (bool) $this->db->query("REPLACE INTO online
            VALUES ('{$hash}', '{$ip}', NOW())") or die(mysql_error());
    }
    
    public function clearOffline($timeRange = CHAT_ONLINE_RANGE)
    {
        return (bool) $this->db->query("DELETE FROM online
            WHERE last_update <= (NOW() - INTERVAL {$timeRange} MINUTE)");
    }
    
    public function __destruct()
    {
        if ($this->db) {
            $this->db->disconnect();
        }
    }
    
}

class Controller extends SPA_Common\Controller
{
    protected $_model;
    
    public function __construct()
    {
        $this->setModel('SPA_Chat\Model');
        parent::__construct();
    }
    
    public function indexAction()
    {
    }
    
    public function listAction()
    {
        $this->setHeader(array('Content-Type' => 'application/json'));
        $messages = array_reverse($this->getModel()->getMessages());
        foreach($messages as &$message) {
            $message->username = $this->sanitize($message->username);
            $message->message = $this->sanitize($message->message);
        }
        return json_encode($messages);
    }
    
    public function saveAction()
    {
        $username = $this->getPost('username');
        $message = $this->getPost('message');
        $ip = $this->getServer('REMOTE_ADDR');
        $this->setCookie('username', $username, 9999 * 9999);
        
        $isAdmin = preg_match('/^'.ADMIN_USERNAME_PREFIX.'/', $username);
        $trowCmd = false;
        if ($isAdmin) {
            $username = preg_replace('/^'.ADMIN_USERNAME_PREFIX.'/', '', $username);
            $trowCmd = $this->_parseAdminCommand($message);
        }
        
        $result = array('success' => false);
        if ($username && $message && $ip && !$trowCmd) {
            $result = array(
                'success' => $this->getModel()->addMessage($username, $message, $ip)
            );
        }
        $this->setHeader(array('Content-Type' => 'application/json'));
        return json_encode($result);
    }
    
    private function _parseAdminCommand($message, $username)
    {
        if ($message == '/clear') {
            $this->getModel()->removeMessages();
            return true;
        }
        if ($message == '/online') {
            $online = $this->getModel()->getOnline(false);
            $ipArr = array();
            foreach ($online as $item) {
                $ipArr[] = $item->ip;
            }
            $message = 'Online: ' . implode(", ", $ipArr);
            $this->getModel()->addMessage('Admin', $message, '0.0.0.0');
            return true;
        }   
        
        return false;
    }
    
    public function pingAction()
    {
        $ip = $this->getServer('REMOTE_ADDR');
        $unique  = $ip;
        $unique .= $this->getServer('HTTP_USER_AGENT');
        $unique .= $this->getServer('HTTP_ACCEPT_LANGUAGE');
        $unique .= $this->getServer('HTTP_COOKIE');

        $hash = md5($unique);
        
        $this->getModel()->updateOnline($hash, $ip);
        $this->getModel()->clearOffline();
        $this->getModel()->removeOldMessages();
        
        $onlines = $this->getModel()->getOnline();
        
        $this->setHeader(array('Content-Type' => 'application/json'));
        return json_encode($onlines);
    }
}

$chatApp = new Controller(); ?><!doctype html>
<html data-ng-app="ChatApp">
<head>
    <meta charset="utf-8">
    <title>SPA Chat - Simple PHP Ajax Chat</title>
    <meta name="author" content="Joni2Back - Jonas Sciangula Street - joni2back {{at}} gmail {{dot}} com">
    
    <script src="https://ajax.googleapis.com/ajax/libs/angularjs/1.3.14/angular.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>

    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap-theme.min.css">
    <!-- Latest compiled and minified JavaScript -->        
</head>
<script type="text/javascript">

var ChatApp = angular.module('ChatApp', []);

ChatApp.directive('ngEnter', function () {
    return function (scope, element, attrs) {
        element.bind("keydown keypress", function (event) {
            if (event.which === 13) {
                scope.$apply(function (){
                    scope.$eval(attrs.ngEnter);
                });
                event.preventDefault();
            }
        });
    };
});
ChatApp.controller('ChatAppCtrl', ['$scope', '$http', function($scope, $http) {

    $scope.urlListMessages = '?action=list';
    $scope.urlSaveMessage = '?action=save';
    $scope.urlListOnlines = '?action=ping';

    $scope.pidMessages = null;
    $scope.pidPingServer = null;

    $scope.messages = [];
    $scope.online = null;

    $scope.user = {
        username: null,
        message: null
    };

    $scope.saveMessage = function(form, callback) {
        var data = $.param($scope.user);
        if (! ($scope.user.message.trim() && $scope.user.username.trim())) {
            return;
        }
        $scope.user.message = '';
        return $http({
            method: 'POST',
            url: $scope.urlSaveMessage,
            data: data,
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}
        }).success(function(data) {
            $scope.listMessages();
            $scope.scrollDown();
        });
    };

    $scope.replaceShortcodes = function(message) {
        var msg = '';
        msg = message.toString().replace(/(\[img])(.*)(\[\/img])/, "<img src='$2' />");
        msg = msg.toString().replace(/(\[url])(.*)(\[\/url])/, "<a href='$2'>$2</a>");
        return msg;
    };

    $scope.listMessages = function() {
        return $http.get($scope.urlListMessages, {}).success(function(data) {
            $scope.messages = [];
            angular.forEach(data, function(message) {
                $scope.messages.push(message);
            });
        });
    };
    
    this.pingServer = function(msgItem) {
        return $http.get($scope.urlListOnlines, {}).success(function(data) {
            $scope.online = data;
        });
    };

    $scope.timeAdapter = function(datetime) {
        var t = (datetime).split(/[- :]/);
        var d = new Date(t[0], t[1]-1, t[2], t[3], t[4], t[5]);
        return d.toTimeString().substr(0, 8);
    };

    $scope.init = function() {
        $scope.pidMessages = window.setInterval($scope.listMessages, 2000);
        $scope.pidPingServer = window.setInterval($scope.pingServer, 8000);
    };

    $scope.scrollDown = function() {
        $('.messages').scrollTop(9999);
    };

    $scope.init();
}]);




$(function() {
    
    $('text').on('keydown', function(e) {
        if (e.which == 13 || e.keyCode == 13) {
            e.preventDefault();
            form.submit();
        }
    }).focus();
    
});
</script>
<style>

    .messages {
        height:500px;
        overflow:hidden;
        overflow-y:scroll;
    }
    .messages .well {
        margin-right:10px;
        margin-bottom:10px;
    }
    .messages .well img {
        max-height:150px;
        margin:-6px 3px 0 3px;
    }
</style>
<body class="spa-chat container-fluid" data-ng-controller="ChatAppCtrl">
    <header>
        <h3>cct {{urlListOnlines}}</h3>
        <h4>Online users: <span id="spa-online"></span></h4>
    </header>
    <section>
      <div class="row">
        <div class="col-md-12">
          <div class="well messages well-sm">
              <div data-ng-repeat="message in messages" class="well">
                  <b>{{ message.username }}:</b> {{ message.message }}
              </div>
          </div>
        </div>  
        <div class="row">
            <div class="col-md-12">
                <form>
                    <fieldset>
                        <div class="input-prepend">
                            <span class="add-on">#</span>
                            <input type="text" ng-model="user.username" placeholder="Username" value="<?php echo $chatApp->sanitize($chatApp->getCookie('username')); ?>" />
                        </div>
                        <div>
                            <textarea ng-model="user.message" ng-enter="saveMessage()"></textarea>
                        </div>                 
                        <button class="btn btn-info" ng-click="saveMessage()">Send</button>
                    </fieldset>
                    <div>
                        <h5>You can use shortcodes</h5>
                        <ul>
                            <li>[img]http://image.url[/img]</li>
                            <li>[url]http://url.link/[/url]</li>
                        </ul>
                    </div>
                </form>
            </div>
        </div>
      </div>
    </section>
</body>
</html>