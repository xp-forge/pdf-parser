<?php namespace com\adobe\pdf;

use io\streams\MemoryInputStream;
use io\streams\compress\InflatingInputStream;
use lang\{Value, IllegalArgumentException};
use util\Objects;

/** @test com.adobe.pdf.unittest.StreamTest */
class Stream implements Value {
  const ZLIB_HEADER= 2;

  private $bytes, $filters;

  /**
   * Creates a new stream
   *
   * @param  string $bytes
   * @param  string[] $filters
   */
  public function __construct($bytes, $filters= []) {
    $this->bytes= $bytes;
    $this->filters= (array)$filters;
  }

  /** @return string */
  public function bytes($filters= true) {
    if (!$filters || empty($this->filters)) return $this->bytes;

    $bytes= $this->bytes;
    foreach ($this->filters as $filter) {
      switch ($filter) {
        case 'FlateDecode':
          $bytes= gzinflate(substr($bytes, self::ZLIB_HEADER));
          break;

        default:
          throw new IllegalArgumentException('Unsupported filter '.$filter);
      }
    }
    return $bytes;
  }

  /** @return io.streams.InputStream */
  public function input($filters= true) {
    $input= new MemoryInputStream($this->bytes);
    if (!$filters || empty($this->filters)) return $input;

    foreach ($this->filters as $filter) {
      switch ($filter) {
        case 'FlateDecode':
          $input->read(self::ZLIB_HEADER);
          $input= new InflatingInputStream($input);
          break;

        default:
          throw new IllegalArgumentException('Unsupported filter '.$filter);
      }
    }
    return $input;
  }

  /** @return string */
  public function hashCode() {
    return 'S'.md5($this->bytes);
  }

  /** @return string */
  public function toString() {
    return sprintf(
      '%s(%d bytes %s)',
      nameof($this),
      strlen($this->bytes),
      $this->filters ? implode(' > ', $this->filters) : 'Plain'
    );
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