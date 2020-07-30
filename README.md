# TradingViewWebsocket

This package allows you to receive quotes from tradingview in real time from websocket. You can get any pair that is on the site. Working in real time allows you to process quotes as quickly as possible.

# How do I run the code?

Сheck example.php file - there is an example of how this class works.
```php
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
```

# What response will TradingView send?

The response will contain all the necessary data, including the last price:
```
array(1) {
  ["EURUSD"]=>
  array(23) {
    ["bid"]=>
    float(1.1831)
    ["ask"]=>
    float(1.18311)
    ["volume"]=>
    int(379938)
    ["update_mode"]=>
    string(9) "streaming"
    ["type"]=>
    string(5) "forex"
    ["short_name"]=>
    string(6) "EURUSD"
    ["pro_name"]=>
    string(9) "FX:EURUSD"
    ["pricescale"]=>
    int(100000)
    ["prev_close_price"]=>
    float(1.17914)
    ["original_name"]=>
    string(9) "FX:EURUSD"
    ["open_price"]=>
    float(1.17914)
    ["minmove2"]=>
    int(10)
    ["minmov"]=>
    int(1)
    ["lp"]=>
    float(1.18309)
    ["low_price"]=>
    float(1.17311)
    ["is_tradable"]=>
    bool(true)
    ["high_price"]=>
    float(1.18342)
    ["fractional"]=>
    bool(false)
    ["exchange"]=>
    string(4) "FXCM"
    ["description"]=>
    string(19) "Euro Fx/U.S. Dollar"
    ["current_session"]=>
    string(6) "market"
    ["chp"]=>
    float(0.33)
    ["ch"]=>
    float(0.00395)
  }
}
```
