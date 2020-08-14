<?php
require 'class.php';

class Main {

  public function __construct($login = null, $password = null)
  {
    $ws = new TradingViewWebsocket(__CLASS__, $login, $password);
  }

  public static function readQuotes($obj) {
    global $array_tmp;
    $array_tickers = [
      "BINANCE:BTCUSDT", "HUOBI:BTCUSDT", "BITTREX:BTCUSDT", "BYBIT:BTCUSDT", "POLONIEX:BTCUSDT", "OKEX:BTCUSDT", "HITBTC:BTCUSDT",
      "KUCOIN:BTCUSDT", "FTX:BTCUSDT", "OKCOIN:BTCUSDT", "BITSTAMP:BTCUSD", "COINBASE:BTCUSD", "BITFINEX:BTCUSD", "BYBIT:BTCUSD",
      "BITBAY:BTCUSD", "GEMINI:BTCUSD", "FTX:BTCUSD", "OKCOIN:BTCUSD"
    ];

    foreach($array_tickers as $ticker) {
      $obj->registerTicker($ticker); // Add ticker
    }

    foreach($obj->tickerData as $data){
      if($array_tmp[$data["original_name"]] != $data["lp"]) {
        var_dump(date('H:i:s ') . $data["original_name"] . ' - ' . $data["lp"]); // Read info from websocket
        $array_tmp[$data["original_name"]] = $data["lp"];
      }
    }
  }
}

$array_tmp = [];
new Main('Login', 'Password');
