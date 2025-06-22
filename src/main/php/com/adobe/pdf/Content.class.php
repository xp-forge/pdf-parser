<?php namespace com\adobe\pdf;

use io\streams\{InputStream, SequenceInputStream};

class Content {
  private $tokens;

  /** @param io.streams.InputStream|com.adobe.Stream... $in */
  public function __construct(... $in) {
    if (1 === sizeof($in)) {
      $this->tokens= new Tokens($in[0]);
    } else {
      $streams= [];
      foreach ($in as $arg) {
        $streams[]= $arg instanceof InputStream ? $arg : $arg->input();
      }
      $this->tokens= new Tokens(new SequenceInputStream($streams));
    }
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