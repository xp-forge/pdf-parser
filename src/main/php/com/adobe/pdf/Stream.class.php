<?php namespace com\adobe\pdf;

use io\streams\{InputStream, MemoryInputStream};
use lang\Value;
use util\Objects;

class Stream implements Value, InputStream {
  private $bytes, $filter;
  private $input= null;

  public function __construct($bytes, $filter) {
    $this->bytes= $bytes;
    $this->filter= $filter;
  }

  public function bytes() {
    switch ($this->filter) {
      case null: return $this->bytes;
      case 'DCTDecode': case 'JPXDecode': case 'CCITTFaxDecode': return $this->bytes;
      case 'FlateDecode': return gzuncompress($this->bytes);
      default: throw new IllegalArgumentException('Unknown filter '.$this->filter);
    }
  }

  /** @return io.streams.InputStream */
  private function input() {
    if ('FlateDecode' === $this->filter) {
      return new MemoryInputStream(gzuncompress($this->bytes)); // TODO: xp-forge/compress
    } else {
      return new MemoryInputStream($this->bytes);
    }
  }

  public function available() {
    $this->input??= $this->input();
    return $this->input->available();
  }

  public function read($bytes= 8192) {
    $this->input??= $this->input();
    return $this->input->read($bytes= 8192);
  }

  public function close() {
    $this->input && $this->input->close();
  }

  public function hashCode() {
    return 'S'.md5($this->bytes);
  }

  public function toString() {
    return nameof($this).'('.strlen($this->bytes).' bytes '.($this->filter ?? 'Plain').')';
  }

  public function compareTo($value) {
    return $value instanceof self ? $this->bytes <=> $value->bytes : 1;
  }
}