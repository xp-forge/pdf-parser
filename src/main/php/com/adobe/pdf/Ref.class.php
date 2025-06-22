<?php namespace com\adobe\pdf;

use lang\Value;
use util\Objects;

class Ref implements Value {
  public $number, $generation;

  public function __construct($number, $generation) {
    $this->number= $number;
    $this->generation= $generation;
  }

  /** @return string */
  public function hashCode() {
    return $this->number.'_'.$this->generation;
  }

  /** @return string */
  public function toString() {
    return nameof($this).'('.$this->number.'_'.$this->generation.')';
  }

  /**
   * Comparison
   *
   * @param  var $value
   * @return int
   */
  public function compareTo($value) {
    return $value instanceof self
      ? $this->number.'_'.$this->generation <=> $value->number.'_'.$value->generation
      : 1
    ;
  }
}