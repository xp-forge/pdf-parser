<?php namespace com\adobe\pdf;

use io\streams\InputStream;
use lang\FormatException;

class CharacterMap {
  private $tokens;

  public $width= null;
  public $chars= [], $ranges= [];
  public $codespace= [0x0000, 0xffff];

  public function __construct(InputStream $in) {
    $this->tokens= new Tokens($in);
  }

  private function char($code) {
    return iconv('ucs-4', \xp::ENCODING, pack('N', $code));
  }

  public function parse(): self {
    try {
      do {
        $token= $this->tokens->token();

        // Parse `2 begin[...]` - an enumeration with 2 entries
        if ('integer' === $token[0]) {
          $length= $token[1];
        } else if ('word' === $token[0] && 1 === sscanf($token[1], 'begin%s', $type)) {
          switch ($type) {
            case 'cmap';
              break;

            case 'codespacerange':
              for ($i= 0; $i < $length; $i++) {
                $lo= $this->tokens->token()[1];
                $hi= $this->tokens->token()[1];
                $this->codespace= [hexdec($lo), hexdec($hi)];
              }
              break;

            case 'bfchar':
              for ($i= 0; $i < $length; $i++) {
                $src= $this->tokens->token()[1];
                $map= $this->tokens->token()[1];
                $this->chars[hexdec($src)]= iconv('utf-16be', \xp::ENCODING, hex2bin($map));
              }
              $this->width??= strlen($src);
              break;

            case 'bfrange': case 'cidrange':
              for ($i= 0; $i < $length; $i++) {
                $lo= $this->tokens->token()[1];
                $hi= $this->tokens->token()[1];

                $token= $this->tokens->token();
                if ('array-start' === $token[0]) {
                  $map= [];
                  next: $token= $this->tokens->token();
                  if ('hex' === $token[0]) {
                    $map[]= hexdec($token[1]);
                    goto next;
                  } else if ('integer' === $token[0]) {
                    $map[]= $token[1];
                    goto next;
                  }
                } else if ('hex' === $token[0]) {
                  $map= hexdec($token[1]);
                } else {
                  $map= $token[1];
                }
                $this->ranges[]= [hexdec($lo), hexdec($hi), $map];
              }
              $this->width??= strlen($lo);
              break;

            default:
              throw new FormatException('Unknown character map enumeration '.$type);
          }
        }
      } while ('endcmap' !== $token[1]);

      return $this;
    } finally {
      $this->tokens->close();
    }
  }

  public function translate($bytes, $fallback= null) {
    $this->width ?? $this->parse();

    $string= '';
    for ($i= 0, $l= strlen($bytes), $b= ($this->width ?? 2) / 2; $i < $l; $i+= $b) {
      $code= 1 === $b ? ord($bytes[$i]) : unpack('n', $bytes, $i)[1];

      if (null !== ($char= $this->chars[$code] ?? null)) {
        $string.= $char;
      } else if ($code < $this->codespace[0] || $code > $this->codespace[1]) {
        $string.= $this->char($code);
      } else {
        $char= null;
        foreach ($this->ranges as $range) {
          if ($code >= $range[0] && $code <= $range[1]) {
            if (is_array($range[2])) {
              $char= $this->char($range[2][$code - $range[0]]);
            } else {
              $char= $this->char($code - $range[0] + $range[2]);
            }
            break;
          }
        }
        $string.= $char ?? sprintf('\u{%04x}', $code);
      }
    }

    // DEBUG
    // var_dump($this);
    // echo "< $bytes\n";
    // echo "> `", addcslashes($string, "\0..\37!\177..\377"), "`\n";

    return $string;
  }
}