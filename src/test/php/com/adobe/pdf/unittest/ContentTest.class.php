<?php namespace com\adobe\pdf\unittest;

use com\adobe\pdf\Content;
use io\streams\MemoryInputStream;
use test\{Assert, Test};

class ContentTest {

  /** Parses and returns content operations */
  private function parse($content) {
    $content= new Content(new MemoryInputStream($content));
    $r= [];
    foreach ($content->operations() as $op => $arguments) {
      $r[]= [$op => $arguments];
    }
    return $r;
  }

  #[Test]
  public function text_block() {
    Assert::equals(
      [
        ['BT' => []],
        ['Tf' => [['name', 'R13'], ['integer', 60]]],
        ['Tj' => [['string', 'Test']]],
        ['ET' => []],
      ],
      $this->parse(
        "BT\n".
        "/R13 60 Tf\n".
        "(Test)Tj\n".
        "ET\n"
      )
    );
  }

  #[Test]
  public function inline_image() {
    Assert::equals(
      [
        ['BI' => []],
        ['ID' => [['name', 'CS'], ['name', 'RGB'], ['$inline', "GIF89a\n..."]]],
        ['EI' => []],
      ],
      $this->parse(
        "BI\n".
        "/CS/RGB\n".
        "ID GIF89a\n".
        "...\n".
        "EI\n"
      )
    );
  }
}