<?php
require dirname(__FILE__).'/vendor/autoload.php';

class TradingViewWebsocket {
  private $session;
  private $subscriptions;
  private $websocket;
  public $tickerData;
  private $sessionRegistered;
  private $class;

  public function __construct($class)
  {
      $this->class = $class;
      $this->resetWebSocket();
  }

  private function generateSession() {
    return "qs_".$this->generateRandomString(12);
  }

  function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  private function sendMessage($func, $args){
    $this->websocket->send($this->createMessage($func, $args));
  }

  public function registerTicker($ticker){
    if(in_array($ticker, $this->subscriptions)) {
      return;
    }
    $this->subscriptions[] = $ticker;
    $this->websocket->send($this->createMessage("quote_add_symbols", [$this->session, $ticker, ['flags' => ["force_permission"]]]));
  }

  public function unregisterTicker($ticker){
    $index = array_search($ticker, $this->subscriptions);
    if($index === false){
      return;
    }
    unset($this->subscriptions[$index]);
    sort($this->subscriptions);
  }

  private function resetWebSocket(){
    $this->tickerData = [];
    $this->subscriptions = [];
    $this->session = $this->generateSession();
    $this->sessionRegistered = false;
    $this->websocket = new WebSocket\Client("wss://data.tradingview.com/socket.io/websocket", [
      'timeout' => 60, // 1 minute time out
      'headers' => [
        'Origin' => 'https://data.tradingview.com',
      ],
    ]);
    while (true) {
      try {
        $string = $this->websocket->receive();
        $packets = $this->parseMessages($string);
        foreach($packets as $packet){
          if(is_array($packet) and $packet["~protocol~keepalive~"]){
            $this->sendRawMessage("~h~".$packet["~protocol~keepalive~"]);
          } elseif($packet->session_id) {
            $this->sendMessage("set_auth_token", ["unauthorized_user_token"]);
            $this->sendMessage("quote_create_session", [$this->session]);
            $this->sendMessage("quote_set_fields", [
              $this->session,
              "ch",
              "chp",
              "current_session",
              "description",
              "local_description",
              "language",
              "exchange",
              "fractional",
              "is_tradable",
              "lp",
              "minmov",
              "minmove2",
              "original_name",
              "pricescale",
              "pro_name",
              "short_name",
              "type",
              "update_mode",
              "volume",
              "ask",
              "bid",
              "fundamentals",
              "high_price",
              "is_tradable",
              "low_price",
              "open_price",
              "prev_close_price",
              "rch",
              "rchp",
              "rtc",
              "status",
              "basic_eps_net_income",
              "beta_1_year",
              "earnings_per_share_basic_ttm",
              "industry",
              "market_cap_basic",
              "price_earnings_ttm",
              "sector",
              "volume",
              "dividends_yield"
            ]);
            $this->sessionRegistered = true;
          } elseif ($packet->m && $packet->m === "qsd" && isset($packet->p) && $packet->p[0] === $this->session) {
            $tticker = $packet->p[1];
            $tickerName = $tticker->n;
            $tickerStatus = $tticker->s;
            $tickerUpdate = $tticker->v;
            foreach ($tickerUpdate as $key => $value)
              $this->tickerData[$tickerName][$key] = $value;
            }
          }
          call_user_func(array($this->class, 'readQuotes'), $this);
      } catch (\WebSocket\ConnectionException $e) {

      }
    }
  }

  private function sendRawMessage($message){
    $this->websocket->send($this->prependHeader($message));
  }

  // IO methods
  private function parseMessages($str){
    $packets = [];
    while(strlen($str) > 0){
      preg_match('/~m~(\d+)~m~/', $str, $x);
      $packet = $this->str_slice($str, strlen($x[0]), strlen($x[0])+$x[1]);
      if(substr($packet, 0, 3) != "~h~"){
        $packets[] = json_decode($packet);
      } else {
        $packets[] = ["~protocol~keepalive~" => substr($packet, 3)];
      }
      $str = $this->str_slice($str, strlen($x[0])+$x[1]);
    }
    return $packets;
  }

  private function prependHeader($str){
    return "~m~".strlen($str)."~m~".$str;
  }

  private function createMessage($func, $paramList){
    return $this->prependHeader($this->constructMessage($func, $paramList));
  }

  private function constructMessage($func, $paramList){
    return json_encode([
      'm' => $func,
      'p' => $paramList
    ]);
  }

  function str_slice() {
    $args = func_get_args();
    switch (count($args)) {
      case 1:
        return $args[0];
      case 2:
        $str = $args[0];
        $str_length = strlen($str);
        $start = $args[1];
        if ($start < 0) {
          if ($start >= -$str_length) {
            $start = $str_length - abs($start);
          } else {
            $start = 0;
          }
        }
        else if ($start >= $str_length) {
          $start = $str_length;
        }
        $length = $str_length - $start;
        return substr($str, $start, $length);
      case 3:
        $str = $args[0];
        $str_length = strlen($str);
        $start = $args[1];
        $end = $args[2];
        if ($start >= $str_length) {
          return "";
        }
        if ($start < 0) {
          if ($start < -$str_length) {
            $start = 0;
          } else {
            $start = $str_length - abs($start);
          }
        }
        if ($end <= $start) {
          return "";
        }
        if ($end > $str_length) {
          $end = $str_length;
        }
        $length = $end - $start;
        return substr($str, $start, $length);
    }
    return null;
  }
}