<?php namespace com\adobe\pdf;

use io\streams\{InputStream, SequenceInputStream};

class Content {
  private $tokens;

  /** @param io.streams.InputStream|com.adobe.Stream... $sources */
  public function __construct(... $sources) {
    if (1 === sizeof($sources)) {
      $this->tokens= new Tokens($sources[0]);
    } else {
      $streams= [];
      foreach ($sources as $source) {
        $streams[]= $source instanceof InputStream ? $source : $source->input();
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