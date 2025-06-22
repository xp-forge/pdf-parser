<?php namespace com\adobe\pdf\unittest;

use com\adobe\pdf\CharacterMap;
use io\streams\MemoryInputStream;
use test\{Assert, Test, Values};

class CharacterMapTest {
  private $definition= <<<'MULTIBYTE'
    /CIDInit /ProcSet findresource begin
    12 dict begin
    begincmap
    /CIDSystemInfo
    << /Registry (Adobe)
    /Ordering (UCS)
    /Supplement 0
    >> def
    /CMapName
    /Adobe-Identity-UCS def
    /CMapType 2 def
    1 begincodespacerange
    <0000> <FFFF>
    endcodespacerange
    4 beginbfchar
    <0003> <0054>
    <000F> <0065>
    <0011> <0073>
    <0012> <007400650064>
    endbfchar
    2 beginbfrange
    <0013> <001C> <0030>
    <03E0> <03E1> [<002C> <003A>]
    endbfrange
    endcmap
    CMapName currentdict /CMap defineresource pop
    end
    end
  MULTIBYTE;

  /** @return iterable */
  private function formats() {
    yield [new MemoryInputStream($this->definition), 'multiline'];
    yield [new MemoryInputStream(strtr($this->definition, "\n", ' ')), 'compact'];
  }

  #[Test, Values(from: 'formats')]
  public function parse($stream, $kind) {
    $fixture= (new CharacterMap($stream))->parse();

    Assert::equals(4, $fixture->width);
    Assert::equals([0x0000, 0xffff], $fixture->codespace);
    Assert::equals([0x0003 => 'T', 0x000F => 'e', 0x0011 => 's', 0x0012 => 'ted'], $fixture->chars);
    Assert::equals([[0x0013, 0x001C, 0x0030], [0x03E0, 0x03E1, [0x002C, 0x003A]]], $fixture->ranges);
  }

  #[Test]
  public function translate_hex_string() {
    $fixture= new CharacterMap(new MemoryInputStream($this->definition));
    Assert::equals('Tested19:', $fixture->translate(hex2bin('0003000F001100120014001C03E1')));
  }
}