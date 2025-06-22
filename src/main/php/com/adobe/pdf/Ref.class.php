<?php namespace com\adobe\pdf;

use lang\Value;
use util\Objects;

class Ref implements Value {
  public $number, $generation;

  public function __construct($number, $generation) {
    $this->number= $number;
    $this->generation= $generation;
  }

  public function hashCode() {
    return $this->number.'_'.$this->generation;
  }

  public function toString() {
    return nameof($this).'('.$this->number.'_'.$this->generation.')';
  }

  public function compareTo($value) {
    return $value instanceof self ? $this->hashCode() <=> $value->hashCode() : 1;
  }
}