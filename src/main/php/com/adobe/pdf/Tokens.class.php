<?php namespace com\adobe\pdf;

use io\streams\InputStream;
use lang\FormatException;

class Tokens {
  private $in;
  private $buffer= '';

  public function __construct(InputStream|Stream $in) {
    $this->in= $in instanceof InputStream ? $in : $in->input();
  }

  public function push(string $buffer) {
    $this->buffer= $buffer.$this->buffer;
  }

  public function bytes(int $length): ?string {
    if (null === $this->buffer) return null;

    while (strlen($this->buffer) < $length && $this->in->available()) {
      $this->buffer.= $this->in->read();
    }

    $return= substr($this->buffer, 0, $length);
    $this->buffer= substr($this->buffer, $length);
    return $return;
  }

  public function line(): ?string {
    if (null === $this->buffer) return null;

    $eof= false;
    do {
      $p= strcspn($this->buffer, "\r\n");
      if ($p < strlen($this->buffer) - 1 || $eof= !$this->in->available()) break;
      $this->buffer.= $this->in->read();
    } while (true);

    // Check for Mac OS, Windows and Un*x line endings
    if ("\r" === ($this->buffer[$p] ?? null)) {
      $p >= strlen($this->buffer) && $this->buffer.= $this->in->read(1);
      $e= "\n" === ($this->buffer[$p + 1] ?? null) ? 2 : 1;
    } else {
      $e= 1;
    }

    $return= substr($this->buffer, 0, $p);
    $this->buffer= $eof ? null : substr($this->buffer, $p + $e);
    return $return;
  }

  public function scan(string $marker): string {
    if (null === $this->buffer) return null;

    while (false === ($p= strpos($this->buffer, $marker))) {
      if (!$this->in->available()) break;
      $this->buffer.= $this->in->read();
    }

    $return= substr($this->buffer, 0, $p);
    $this->buffer= substr($this->buffer, $p);
    return $return;
  }

  public function token($peek= false): ?array {
    if (null === $this->buffer) return null;

    // Read a complete line
    $eof= false;
    value: do {
      $p= strcspn($this->buffer, "\n");
      if ($p < strlen($this->buffer) - 1 || $eof= !$this->in->available()) break;
      $this->buffer.= $this->in->read();
    } while (true);

    // echo '=> `', addcslashes($this->buffer, "\0..\37!\177..\377"), "`\n";
    if ('' === $this->buffer && $eof) return $this->buffer= null;

    if ('/' === $this->buffer[0]) {
      $p= strspn($this->buffer, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789#:.,+-_', 1);
      $r= ['name', substr($this->buffer, 1, $p)];
      $p++;
    } else if ('(' === $this->buffer[0]) {
      $string= '';
      $offset= $braces= 1;

      segment: $p= strcspn($this->buffer, '\\()', $offset);
      $string.= substr($this->buffer, $offset, $p);
      $offset+= $p;
      if ('\\' === $this->buffer[$offset]) {
        $offset++;

        // Handle character sequences like `\374`
        if (3 === strspn($this->buffer, '0123456789', $offset)) {
          $string.= chr(octdec(substr($this->buffer, $offset, 3)));
          $offset+= 3;
        } else {
          $string.= $this->buffer[$offset++];
        }
        goto segment;
      } else if ('(' === $this->buffer[$offset]) {
        $offset++;
        if (++$braces) {
          $string.= '(';
          goto segment;
        }
      } else if (')' === $this->buffer[$offset]) {
        $offset++;
        if (--$braces) {
          $string.= ')';
          goto segment;
        }
      }
      $p= $offset;
      $r= ['string', $string];
    } else if ('[' === $this->buffer[0]) {
      $p= 1;
      $r= ['array-start', null];
    } else if (']' === $this->buffer[0]) {
      $p= 1;
      $r= ['array-end', null];
    } else if ('%' === $this->buffer[0]) {
      $p= strcspn($this->buffer, "\r\n", 1);
      $r= ['comment', substr($this->buffer, 1 + strspn($this->buffer, ' ', 1), $p - 1)];
      $p++;
    } else if (0 === strncmp($this->buffer, '<<', 2)) {
      $p= 2;
      $r= ['dict-start', null];
    } else if (0 === strncmp($this->buffer, '>>', 2)) {
      $p= 2;
      $r= ['dict-end', null];
    } else if ('<' === $this->buffer[0]) {
      $p= strpos($this->buffer, '>');
      $r= ['hex', substr($this->buffer, 1, $p - 1)];
      $p++;
    } else if ($p= strspn($this->buffer, '-0123456789.')) {

      // Disambiguate references from integer and decimal numbers
      if (4 === sscanf($this->buffer, '%d %d %*[R]%n', $number, $generation, $l)) {
        $p= $l;
        $r= ['ref', new Ref($number, $generation)];
      } else {
        $number= substr($this->buffer, 0, $p);
        $r= false === strpos($number, '.') ? ['integer', (int)$number] : ['decimal', (float)$number];
      }
    } else if (0 === strncmp($this->buffer, 'true', 4)) {
      $p= 4;
      $r= ['const', true];
    } else if (0 === strncmp($this->buffer, 'null', 4)) {
      $p= 4;
      $r= ['const', null];
    } else if (0 === strncmp($this->buffer, 'false', 5)) {
      $p= 5;
      $r= ['const', false];
    } else if (0 === strncmp($this->buffer, 'stream', 6)) {

      // Stream starts on a new line
      $p= 6 + strspn($this->buffer, "\r\n", 6);
      $r= ['stream-start', null];
    } else if (0 === strncmp($this->buffer, 'endstream', 9)) {
      $p= 9;
      $r= ['stream-end', null];
    } else if ($p= strspn($this->buffer, " \t\r\n")) {
      $this->buffer= substr($this->buffer, $p);
      goto value;
    } else {

      // Everything until the next whitespace or begin of a token
      $p= strcspn($this->buffer, " \t\r\n/<[(%");
      $r= ['word', substr($this->buffer, 0, $p)];
    }

    $peek || $this->buffer= substr($this->buffer, $p);
    // var_dump($r);
    return $r;
  }

  public function expect($kind) {
    $token= $this->token();
    if ($kind !== $token[0]) {
      throw new FormatException(sprintf(
        'Expected %s, have %s `%s...`',
        $kind,
        $token[0],
        substr(addcslashes($token[1], "\0..\37!\177..\377"), 0, 42)
      ));
    }
    return $token;
  }

  public function value($token= null) {
    $token??= $this->token();
    if ('array-start' === $token[0]) {
      $array= [];
      element: if (null === ($token= $this->token())) throw new FormatException('unclosed array');
      if ('array-end' !== $token[0]) {
        $array[]= $this->value($token);
        goto element;
      }
      return $array;
    } else if ('dict-start' === $token[0]) {
      $object= [];
      pair: if (null === ($token= $this->token())) throw new FormatException('unclosed dict');
      if ('dict-end' !== $token[0]) {
        $object[$token[1]]= $this->value();
        goto pair;
      }

      // Handle stream objects
      $token= $this->token(true);
      if (['stream-start', null] === $token) {
        $this->expect('stream-start');
        if (is_int($object['Length'])) {
          $bytes= $this->bytes($object['Length']);
        } else {
          $bytes= $this->scan("endstream\n");
        }
        $this->expect('stream-end');

        $object['$stream']= new Stream($bytes, $object['Filter'] ?? null);
      }
      return $object;
    } else if ('string' === $token[0]) {
      if (0 === strncmp("\xfe\xff", $token[1], 2)) {
        return iconv('utf-16be', \xp::ENCODING, substr($token[1], 2));
      } else {
        return $token[1];
      }
    } else {
      return $token[1];
    }
  }

  public function close() {
    $this->in->close();
  }
}