<?php namespace com\adobe\pdf;

use io\streams\InputStream;

class Content {
  private $tokens;

  public function __construct(InputStream $in) {
    $this->tokens= new Tokens($in);
  }

  public function operations(): iterable {
    $operands= [];
    while (null !== ($token= $this->tokens->token())) {
      if ('word' === $token[0]) {

        // Attach inline image data
        if ('ID' === $token[1]) {
          $operands[]= ['$inline', trim($this->tokens->scan("\nEI"))];
        }

        yield $token[1] => $operands;
        $operands= [];
      } else {
        $operands[]= $token;
      }
    }
    $this->tokens->close();
  }
}