<?php namespace com\adobe\pdf\unittest;

use com\adobe\pdf\Stream;
use io\streams\Streams;
use test\{Assert, Test};

class StreamTest {
  const PLAIN= 'Test';
  const DEFLATED= "\x78\x9c\x0b\x49\x2d\x2e\x01\x00";

  #[Test]
  public function plain_bytes() {
    Assert::equals(self::PLAIN, (new Stream(self::PLAIN))->bytes());
  }

  #[Test]
  public function plain_stream() {
    Assert::equals(self::PLAIN, Streams::readAll((new Stream(self::PLAIN))->input()));
  }

  #[Test]
  public function plain_string_representation() {
    Assert::equals(#
      'com.adobe.pdf.Stream(4 bytes Plain)',
      (new Stream(self::PLAIN))->toString()
    );
  }

  #[Test]
  public function flatedecode_bytes() {
    Assert::equals(self::PLAIN, (new Stream(self::DEFLATED, 'FlateDecode'))->bytes());
  }

  #[Test]
  public function flatedecode_stream() {
    Assert::equals(self::PLAIN, Streams::readAll((new Stream(self::DEFLATED, 'FlateDecode'))->input()));
  }

  #[Test]
  public function flatedecode_string_representation() {
    Assert::equals(
      'com.adobe.pdf.Stream(8 bytes FlateDecode)',
      (new Stream(self::DEFLATED, 'FlateDecode'))->toString()
    );
  }
}