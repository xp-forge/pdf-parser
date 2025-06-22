<?php namespace com\adobe\pdf\unittest;

use com\adobe\pdf\CharacterMap;
use io\streams\MemoryInputStream;
use test\{Assert, Before, Test, Values};

class CharacterMapTest {
  private $definition;

  #[Before]
  public function definition() {
    $this->definition= (
      "/CIDInit /ProcSet findresource begin\n".
      "12 dict begin\n".
      "begincmap\n".
      "/CIDSystemInfo\n".
      "<< /Registry (Adobe)\n".
      "/Ordering (UCS)\n".
      "/Supplement 0\n".
      ">> def\n".
      "/CMapName\n".
      "/Adobe-Identity-UCS def\n".
      "/CMapType 2 def\n".
      "1 begincodespacerange\n".
      "<0000> <FFFF>\n".
      "endcodespacerange\n".
      "4 beginbfchar\n".
      "<0003> <0054>\n".
      "<000F> <0065>\n".
      "<0011> <0073>\n".
      "<0012> <007400650064>\n".
      "endbfchar\n".
      "2 beginbfrange\n".
      "<0013> <001C> <0030>\n".
      "<03E0> <03E1> [<002C> <003A>]\n".
      "endbfrange\n".
      "endcmap\n".
      "CMapName currentdict /CMap defineresource pop\n".
      "end\n".
      "end\n"
    );
  }

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