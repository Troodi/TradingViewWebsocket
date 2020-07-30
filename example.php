<?php
require 'class.php';

class Main {
  public function __construct()
  {
    $ws = new TradingViewWebsocket(__CLASS__);
  }

  public static function readQuotes($obj) {
    $obj->registerTicker("EURUSD"); // Add ticker
    var_dump($obj->tickerData); // Read info from websocket
  }
}

new Main();