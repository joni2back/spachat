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
define('DB_NAME',           'chat');
define('CHAT_HISTORY',      '100');
define('CHAT_ONLINE_RANGE', '1');

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
    
    public function getOnline($timeRange = CHAT_ONLINE_RANGE)
    {
        $response = $this->db->query("SELECT count(*) as total FROM online");
        return $response->getOne();
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
        
        $result = array('success' => false);
        if ($username && $message && $ip) {
            $this->setCookie('username', $username, 9999 * 9999);
            $result = array(
                'success' => $this->getModel()->addMessage($username, $message, $ip)
            );
        }
        
        $this->setHeader(array('Content-Type' => 'application/json'));
        return json_encode($result);
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
        
        $onlines = $this->getModel()->getOnline();
        
        $this->setHeader(array('Content-Type' => 'application/json'));
        return json_encode($onlines);
    }
}

$chatApp = new Controller(); ?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>SPA Chat - Simple PHP Ajax Chat</title>
    <meta name="author" content="Joni2Back - Jonas Ramiro Sciangula Street - joni2back {{at}} gmail {{dot}} com">
    
    <script src="//ajax.googleapis.com/ajax/libs/jquery/2.0.1/jquery.min.js"></script>
    <script src="bootstrap/js/bootstrap.min.js"></script>
    
    <link href="bootstrap/css/bootstrap.css" rel="stylesheet">
    <link href="bootstrap/css/bootstrap-responsive.css" rel="stylesheet">    
</head>
<script type="text/javascript">

var SPA_Chat = function() 
{
    var that = this;
    this.urlListMessages = '?action=list';
    this.urlSaveMessage = '?action=save';
    this.urlListOnlines = '?action=ping';
    this.lastChange = '';
    
    this.renderMessage = function(msgItem)
    {
        var htmlItem = '';
        date = that.timeAdapter(msgItem.date);
        htmlItem += '<div class="well well-small">';
        htmlItem += '[' + date + '] <b>' +msgItem.username+': </b>';
        htmlItem += msgItem.message;
        htmlItem += '</div>';
        return htmlItem;
    };
    
    this.pingServer = function(msgItem)
    {
        var target = $('#spa-online');
        $.getJSON(that.urlListOnlines, function(data) {
            target.html(data.total);
        });
        return that;
    };
    
    this.timeAdapter = function(datetime)
    {
        var t = (datetime).split(/[- :]/);
        var d = new Date(t[0], t[1]-1, t[2], t[3], t[4], t[5]);
        return d.toTimeString().substr(0, 8);
    };

    this.listMessages = function() 
    {
        var target = $('#spa-chat-messages');
        $.getJSON(that.urlListMessages, function(data) {
            var items = [];
            $.each(data, function(i, msgItem) {
                htmlItem = that.renderMessage(msgItem);
                items.push(htmlItem);
            });
            var current = items.join("\n");
            if (that.lastChange != current) {
                target.html(that.lastChange = current);
            }
        });
        return that;
    };
    
    this.saveMessage = function(form, callback) 
    {
        $.post(that.urlSaveMessage, $(form).serialize(), function(data) {
            that.listMessages();
        });
        return that;
    };
}

$(function() {
    var Chat_App = new SPA_Chat();
    
    var form = $('#spa-chat-form');
    var msgs = $('#spa-chat-messages');
    var text = $('textarea#message');
    
    Chat_App.listMessages().pingServer();
    msgs.animate({scrollTop: 9999});
    
    window.setInterval(Chat_App.listMessages, 2000);
    window.setInterval(Chat_App.pingServer, 3000);
    
    form.submit(function(e) {
        e.preventDefault();
        Chat_App.saveMessage(this, Chat_App.listMessages);
        text.val('');
        msgs.animate({scrollTop: 9999});
    });
    
    text.on('keydown', function(e) {
        if (e.which == 13 || e.keyCode == 13) {
            e.preventDefault();
            form.submit();
        }
    }).focus();
    
});
</script>
<style>

    #spa-chat-messages {
        height:500px;
        overflow:hidden;
        overflow-y:scroll;
    }
    #spa-chat-messages .well {
        margin-right:10px;
        margin-bottom:10px;
    }
    #spa-chat-messages img {
        max-height:150px;
    }
    
    
</style>
<body class="spa-chat container-fluid">
    <header>
        <h3>SPA Chat - Simple PHP Ajax Chat</h3>
        <h4>Online users: <span id="spa-online"></span></h4>
    </header>
    <section>
      <div class="row-fluid">
        <div class="span5">
          <div class="nav" id="spa-chat-messages"></div>
        </div>  
        <div class="span2">
            <form id="spa-chat-form" action="?action=save">
                <fieldset>
                    <div class="input-prepend">
                        <span class="add-on">#</span>
                        <input class="span15" type="text" name="username" placeholder="Username" value="<?php echo $chatApp->sanitize($chatApp->getCookie('username')); ?>" />
                    </div>
                    <div >
                        <textarea cols="6" rows="3" id="message" name="message"></textarea>
                    </div>                 
                    <input type="submit" class="btn btn-info span5" value="Send">
                </fieldset>
            </form>
        </div>
      </div>
    </section>
</body>
</html>