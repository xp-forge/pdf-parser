<?php namespace com\adobe\pdf;

use io\streams\InputStream;
use lang\FormatException;

/** @see https://opensource.adobe.com/dc-acrobat-sdk-docs/pdflsdk/#pdf-reference */
class PdfReader {
  private $tokens;
  public $version;

  public function __construct(InputStream $in) {
    $this->tokens= new Tokens($in);
    if (1 !== sscanf($this->tokens->line(), '%%PDF-%[0-9.]', $this->version)) {
      throw new FormatException('PDF file header not found');
    }
  }

  private function xref() {
    $xref= [];
    while (2 === sscanf($line= $this->tokens->line(), '%d %d', $number, $length)) {
      for ($i= 0; $i < $length; $i++) {
        $xref[]= $this->tokens->line();
      }
    }
    $this->tokens->push($line."\n");
    return $xref;
  }

  public function objects() {
    $operands= [];
    while (null !== ($token= $this->tokens->token())) {
      if ('word' === $token[0]) {
        if ('obj' === $token[1]) {
          $number= $operands[0][1];
          $generation= $operands[1][1];
          yield 'object' => ['id' => new Ref($number, $generation), 'dict' => $this->tokens->value()];
        } else if ('xref' === $token[1]) {
          yield 'xref' => $this->xref();
        } else if ('trailer' === $token[1]) {
          yield 'trailer' => $this->tokens->value();
        }
        $operands= [];
      } else if ('comment' !== $token[0]) {
        $operands[]= $token;
      }
    }
  }
}
