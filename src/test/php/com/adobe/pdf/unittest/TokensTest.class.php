<?php namespace com\adobe\pdf\unittest;

use com\adobe\pdf\{Tokens, Ref};
use io\streams\InputStream;
use test\{Assert, Ignore, Test, Values};

class TokensTest {

  /** @param string|string[] $chunks */
  private function fixture($chunks): Tokens {
    return new Tokens(new class((array)$chunks) implements InputStream {
      private $chunks;
      public function __construct($chunks) { $this->chunks= $chunks; }
      public function available() { return sizeof($this->chunks); }
      public function read($bytes= 8192) { return array_shift($this->chunks); }
      public function close() { $this->chunks= []; }
    });
  }

  #[Test]
  public function read_twice() {
    $fixture= $this->fixture('Tested');
    Assert::equals(['Test', 'ed'], [$fixture->bytes(4), $fixture->bytes(2)]);
  }

  #[Test]
  public function read_empty() {
    $fixture= $this->fixture('');
    Assert::equals('', $fixture->bytes(1));
  }

  #[Test, Values(['Test', "Test\n"])]
  public function line($input) {
    $fixture= $this->fixture($input);
    Assert::equals('Test', $fixture->line());
    Assert::null($fixture->line());
  }

  #[Test, Values(["Line 1\n\nLine 3", "Line 1\r\rLine 3", "Line 1\r\n\r\nLine 3"])]
  public function empty_line($input) {
    $fixture= $this->fixture($input);
    Assert::equals('Line 1', $fixture->line());
    Assert::equals('', $fixture->line());
    Assert::equals('Line 3', $fixture->line());
    Assert::null($fixture->line());
  }

  #[Test, Values([['/Length', 'Length'], ['/ca', 'ca'], ['/S', 'S'], ['/FlateDecode', 'FlateDecode'], ['/C2_0', 'C2_0'], ['/Font,Bold', 'Font,Bold']])]
  public function name($input, $expected) {
    Assert::equals(['name', $expected], $this->fixture($input)->token());
  }

  #[Test, Values([['()', ''], ['(Test)', 'Test']])]
  public function string($input, $expected) {
    Assert::equals(['string', $expected], $this->fixture($input)->token());
  }

  #[Test, Values([['(C:\\\\PHP \\(8.5\\))', 'C:\\PHP (8.5)'], ['(f\\303\\274r)', 'für']])]
  public function escapes_in_string($input, $expected) {
    Assert::equals(['string', $expected], $this->fixture($input)->token());
  }

  #[Test, Values([['(())', '()'], ['(Test (OK))', 'Test (OK)']])]
  public function balanced_braces_in_string($input, $expected) {
    Assert::equals(['string', $expected], $this->fixture($input)->token());
  }

  #[Test, Values([['1', 1], ['-1', -1], ['6100', 6100]])]
  public function integer($input, $expected) {
    Assert::equals(['integer', $expected], $this->fixture($input)->token());
  }

  #[Test, Values([['1.5', 1.5], ['-0.5', -0.5], ['3.141', 3.141]])]
  public function decimal($input, $expected) {
    Assert::equals(['decimal', $expected], $this->fixture($input)->token());
  }

  #[Test]
  public function ref() {
    Assert::equals(['ref', new Ref(62, 0)], $this->fixture('62 0 R')->token());
  }

  #[Test]
  public function comment() {
    Assert::equals(['comment', 'Test'], $this->fixture('% Test')->token());
  }

  #[Test]
  public function hex() {
    Assert::equals(['hex', 'B5FEF09943'], $this->fixture('<B5FEF09943>')->token());
  }

  #[Test, Ignore('Not yet implemented')]
  public function multiline_string() {
    Assert::equals(['Test passed'], $this->fixture("(Test\n passed)")->token());
  }

  #[Test, Values([['true', true], ['false', false], ['null', null]])]
  public function constants($input, $expected) {
    Assert::equals(['const', $expected], $this->fixture($input)->token());
  }

  #[Test, Values(['[]', '[ ]', '[  ]'])]
  public function empty_array($input) {
    $fixture= $this->fixture($input);
    Assert::equals(['array-start', null], $fixture->token());
    Assert::equals(['array-end', null], $fixture->token());
  }

  #[Test, Values(['[[]]', '[ [] ]', '[ [ ] ]'])]
  public function nested_array($input) {
    $fixture= $this->fixture($input);
    Assert::equals(['array-start', null], $fixture->token());
    Assert::equals(['array-start', null], $fixture->token());
    Assert::equals(['array-end', null], $fixture->token());
    Assert::equals(['array-end', null], $fixture->token());
  }

  #[Test, Values(['[1 2]', '[1 2 ]', '[ 1 2]', '[ 1 2 ]', '[ 1  2 ]'])]
  public function array($input) {
    $fixture= $this->fixture($input);
    Assert::equals(['array-start', null], $fixture->token());
    Assert::equals(['integer', 1], $fixture->token());
    Assert::equals(['integer', 2], $fixture->token());
    Assert::equals(['array-end', null], $fixture->token());
  }

  #[Test, Values(['<<>>', '<< >>', '<<  >>'])]
  public function empty_dict($input) {
    $fixture= $this->fixture($input);
    Assert::equals(['dict-start', null], $fixture->token());
    Assert::equals(['dict-end', null], $fixture->token());
  }

  #[Test, Values(['<</Length 3>>', '<< /Length 3>>', '<</Length 3 >>'])]
  public function dict($input) {
    $fixture= $this->fixture($input);
    Assert::equals(['dict-start', null], $fixture->token());
    Assert::equals(['name', 'Length'], $fixture->token());
    Assert::equals(['integer', 3], $fixture->token());
    Assert::equals(['dict-end', null], $fixture->token());
  }

  #[Test]
  public function multiline_dict() {
    $fixture= $this->fixture(["<</Type /StructElem\n", "/S /Link>>"]);
    Assert::equals(['dict-start', null], $fixture->token());
    Assert::equals(['name', 'Type'], $fixture->token());
    Assert::equals(['name', 'StructElem'], $fixture->token());
    Assert::equals(['name', 'S'], $fixture->token());
    Assert::equals(['name', 'Link'], $fixture->token());
    Assert::equals(['dict-end', null], $fixture->token());
  }

  #[Test, Values([[["<<>> stream\nTest\nendstream"]], [["<<>> stream\r\nTest\r\nendstream"]], [["<<>>\nstream\nTest\nendstream"]], [["<<>> stream\n", "Test\nendstream"]], [["<<>>\n", "stream\n", "Test\nendstream"]], [["<<>>", "\n", "stream\n", "Test\nendstream"]]])]
  public function stream_object($chunks) {
    $fixture= $this->fixture($chunks);
    Assert::equals(['dict-start', null], $fixture->token());
    Assert::equals(['dict-end', null], $fixture->token());
    Assert::equals(['stream-start', null], $fixture->token());
    Assert::equals('Test', $fixture->bytes(4));
    Assert::equals(['stream-end', null], $fixture->token());
  }
}