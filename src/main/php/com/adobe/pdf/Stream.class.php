<?php namespace com\adobe\pdf;

use io\streams\MemoryInputStream;
use io\streams\compress\InflatingInputStream;
use lang\Value;
use util\Objects;

/** @test com.adobe.pdf.unittest.StreamTest */
class Stream implements Value {
  const ZLIB_HEADER= 2;

  private $bytes, $filter;

  /**
   * Creates a new stream
   *
   * @param  string $bytes
   * @param  ?string $filter
   */
  public function __construct($bytes, $filter= null) {
    $this->bytes= $bytes;
    $this->filter= $filter;
  }

  /** @return string */
  public function bytes() {
    switch ($this->filter) {
      case null: return $this->bytes;
      case 'DCTDecode': case 'JPXDecode': case 'CCITTFaxDecode': return $this->bytes;
      case 'FlateDecode': return gzinflate(substr($this->bytes, self::ZLIB_HEADER));
      default: throw new IllegalArgumentException('Unknown filter '.$this->filter);
    }
  }

  /** @return io.streams.InputStream */
  public function input() {
    $input= new MemoryInputStream($this->bytes);
    if ('FlateDecode' === $this->filter) {
      $input->read(self::ZLIB_HEADER);
      return new InflatingInputStream($input);
    } else {
      return $input;
    }
  }

  /** @return string */
  public function hashCode() {
    return 'S'.md5($this->bytes);
  }

  /** @return string */
  public function toString() {
    return nameof($this).'('.strlen($this->bytes).' bytes '.($this->filter ?? 'Plain').')';
  }

  /**
   * Comparison
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self ? $this->filter.':'.$this->bytes <=> $value->filter.':'.$value->bytes : 1;
  }
}