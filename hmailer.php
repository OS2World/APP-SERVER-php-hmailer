<?php
   ## 
   ## HMailer class, version 2.1
   ## 
   ## This file is released into the public domain.
   ## 
   ## This file is distributed WITHOUT ANY EXPLICIT OR IMPLIED WARRANTY of
   ## any sort.  For example (but not limited to this example), there
   ## is NO WARRANTY, EXPLICIT OR IMPLIED, OF ANY MERCHANTABILITY OR FITNESS
   ## FOR ANY PARTICULAR PURPOSE.
   ##
   ## VERY LITTLE TESTING HAS BEEN DONE; USE AT YOUR OWN RISK.
   ## 

   class HMailer
   {
      // 
      // Initialize these or redefine in a subclass.
      // 
      var $tmpdir    = '';
      var $exefile   = 'G:\\hmailer\\HMailer.exe';
      var $wrapper   = '';
      var $default_subject = '(no subject)';
      var $default_to = '(Undeclosed recipients)';


      // 
      // Member variable intended as read-only.
      // 
      var $version = '2.1';

      // 
      // Semi-private member variables primarily intended for internal use.
      // 
      var $subject  = '';
      var $mainbody = '';
      var $headers  = '';

      // 
      // Private member variables intended for internal use only.
      // 
      var $b64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

      var $boundry = '';
      var $parts = array();
      var $partsaremade = false;

      var $to      = array();
      var $cc      = array();
      var $bcc     = array();
      var $from    = array();
      var $replyto = array();

      var $last_boundry = '';
      var $last_parts = array();

      var $last_to       = '';
      var $last_cc       = '';
      var $last_bcc      = '';
      var $last_from     = '';
      var $last_replyto  = '';
      var $last_date     = '';
      var $last_subject  = '';
      var $last_message  = '';
      var $last_result   = 0;

      var $last_fail     = '';

      // 
      // mk_addrname -- returns:  "\"$name\" <acct@foobar.com>".
      // 
      // Private function.
      // 
      function mk_addrname( $addr, $name = '' )
      {
         if ( ! $name ) $name = $addr;
         return "\"$name\" <$addr>";
      }


      // encodeqp
      // 
      // Private function.
      //
      // Currently only a stub.
      // 
      function encodeqp ( &$txtstr )
      {
         return chop( $txtstr ) . "\r\n";
      }


      // encode64
      //
      // Private function.
      function encode64( &$binstr )
      {
         $ret = '';
         $tmp = '';
         $count = 0;
         $size = strlen( $binstr );
         $basesize = floor( $size / 3 ) * 3;
         for ( $ii = 0; $ii < $basesize; $ii += 3 ) {
            $n1 =  ord( $binstr[ $ii ] );
            $n2 =  ord( $binstr[ $ii + 1 ] );
            $n3 =  ord( $binstr[ $ii + 2 ] );
            $tmp .= $this->b64[ ($n1 & 0xfc) >> 2 ];
            $tmp .= $this->b64[ (($n1 & 0x03) << 4) + (($n2 & 0xf0) >> 4) ];
            $tmp .= $this->b64[ (($n2 & 0x0f) << 2) + (($n3 & 0xc0) >> 6) ];
            $tmp .= $this->b64[ ($n3 & 0x3f) ];
            if ( $count == 18 ) {
               $count = 0;
               $ret .= "$tmp\r\n";
               $tmp = '';
            }
            else {
               $count++;
            }
         }

         switch ( $size % 3 ) {
            case 1:
               $n1 =  ord( $binstr[ $ii++ ] );
               $tmp .= $this->b64[ ($n1 & 0xfc) >> 2 ];
               $tmp .= $this->b64[ (($n1 & 0x03) << 4) ];
               $tmp .= '==';
               break;
            case 2:
               $n1 =  ord( $binstr[ $ii++ ] );
               $n2 =  ord( $binstr[ $ii++ ] );
               $tmp .= $this->b64[ ($n1 & 0xfc) >> 2 ];
               $tmp .= $this->b64[ (($n1 & 0x03) << 4) + (($n2 & 0xf0) >> 4) ];
               $tmp .= $this->b64[ (($n2 & 0x0f) << 2) ];
               $tmp .= '=';
               break;
            default:
               // break;
         }

         if ( $tmp != '' ) {
            $ret .= "$tmp\r\n";
         }

         return $ret;
      }

      // mk_allparts

      //
      // Private function.
      function mk_allparts()
      {
         $ret = 0;
         reset( $this->parts );
         while ( list( $k ) = each( $this->parts ) ) {
            $ret = $this->mk_onepart( $this->parts[ $k ] );
            if ( $ret != 0 ) {
               break;
            }
         }
         if ( $ret == 0 ) { $this->partsaremade = true; }
         return $ret;
      }

      // mk_onepart
      //
      // Private function.
      function mk_onepart( &$part )
      {
         $ret = 0;
         $content_type  = 'Content-Type: ' .
                          $part[ 'maintype' ] . '/' .
                          $part[ 'subtype' ];
         $paramsstr = '';
         $content_encoding = '';
         $content_description =
               isset( $part[ 'description' ] ) ?
               'Content-Description: ' . $part[ 'description' ] . "\r\n":
               '';

         switch ( strtolower( $part[ 'maintype' ] ) ) {
            case 'text':
               if ( isset( $part[ 'encoding' ] ) ) {
                  $content_encoding = 'Content-Transfer-Encoding: ';
                  switch ( strtolower( $part[ 'encoding' ] ) ) {
                     case '7bit':
                        $content_encoding .= "7bit\r\n";
                        $part[ '_body' ] = chop( $part[ 'content' ] ) . "\r\n";
                        break;

                     case '8bit':
                        $content_encoding .= "8bit\r\n";
                        $part[ '_body' ] = chop( $part[ 'content' ] ) . "\r\n";
                        break;

                     case 'quoted-printable':
                        $content_encoding .= "quoted-printable\r\n";
                        $part[ '_body' ] = $this->encodeqp( $part[ 'content' ] );
                        $ret = -12;
                        break;

                     // case 'base64':
                     // case 'binary':
                     // case 'ietf-token':
                     // case 'x-token':
                     default:
                        $ret = -12;
                        // break;
                  }
               }
               else {
                  $part[ '_body' ] = chop( $part[ 'content' ] ) . "\r\n";
               }
               break;
            
            case 'image':
            case 'audio':
            case 'application':
               if ( ! isset( $part[ 'encoding' ] ) ) {
                  $part[ 'encoding' ] = 'base64';
               }
               if ( isset( $part[ 'encoding' ] ) ) {
                  $content_encoding = 'Content-Transfer-Encoding: ';
                  switch ( strtolower( $part[ 'encoding' ] ) ) {
                     case '7bit':
                        $content_encoding .= "7bit\r\n";
                        $part[ '_body' ] = chop( $part[ 'content' ] ) . "\r\n";
                        break;

                     case '8bit':
                        $content_encoding .= "8bit\r\n";
                        $part[ '_body' ] = chop( $part[ 'content' ] ) . "\r\n";
                        break;

                     case 'quoted-printable':
                        $content_encoding .= "quoted-printable\r\n";
                        $part[ '_body' ] = $this->encodeqp( $part[ 'content' ] );
                        $ret = -12;
                        break;

                     case 'base64':
                        $content_encoding .= "base64\r\n";
                        $part[ '_body' ] = $this->encode64( $part[ 'content' ] );
                        break;

                     // case 'binary':
                     // case 'ietf-token':
                     // case 'x-token':
                     default:
                        $ret = -12;
                        // break
                  }
               }
               break;

            case 'multipart':
            case 'message':
            default:
               $ret = -12;
               // break;
         }

         if ( $ret == 0 ) {
            $paramstr = '';
            if ( isset( $part[ 'typeparams' ] ) ){
               reset( $part[ 'typeparams' ] );
               while ( list( $k, $v ) = each( $part[ 'typeparams' ] ) )
               {
                  $paramstr .= "; $k= \"$v\"";
               }
            }
            $part[ '_headers' ] = "$content_type$paramstr\r\n$content_encoding$content_description\r\n";
         }

         return $ret;
      }


      // 
      // chk_addr -- minimal safety check on e-mail address
      //
      function chk_addr( $addr )
      {
         $a = trim( $addr );
         return preg_match( 
             '/^[-!#$%&\'*+\\.\/0-9=?A-Z^_`{|}~]+' .
             '@' .
             '([-0-9A-Z]+\.)+' .
             '((([0-9A-Z]){2,3})|(arpa))$/i' ,
             $a
         ) ? $a : false;
      }

      // 
      // chk_name -- minimal safety check on e-mail real names
      //
      function chk_name( $name )
      {
         return preg_match( "/[\cA-\cZ&|<>]/", $name ) ?
                false :
                preg_replace( "/([\"'])/", '$1', $name );
      }

      // 
      // setspec -- configure e-mail mesage parameters
      // 
      function setspec( $specarray = false )
      {
         if ( ! $specarray )
         {
            $this->to        = array();
            $this->cc        = array();
            $this->bcc       = array();
            $this->from      = array();
            $this->replyto   = array();
            $this->parts     = array();
            $this->subject   = '';
            $this->mainbody  = '';
            $this->headers   = '';
            return 0;
         }

         reset( $specarray );
         while ( list( $k, $v ) = each( $specarray ) )
         {
            switch ( $k )
            {
               case 'to':
                  $this->to = array();
                  if ( ! $this->add_to( $v ) ) return -1;
                  break;
               case 'cc':
                  $this->cc = array();
                  if ( ! $this->add_cc( $v ) ) return -2;
                  break;
               case 'bcc':
                  $this->bcc = array();
                  if ( ! $this->add_bcc( $v ) ) return -3;
                  break;
               case 'from':
                  $this->from = array();
                  reset( $v );
                  while ( list( $a, $n ) = each( $v ) )
                  {
                     if ( ! $this->set_from( $a, $n ) ) return -4;
                     break;  // Set only one sender.
                  }
                  break;
               case 'replyto':
                  $this->replyto = array();
                  reset( $v );
                  while ( list( $a, $n ) = each( $v ) )
                  {
                     if ( ! $this->set_replyto( $a, $n ) ) return -4;
                     break;  // Set only one replyto.
                  }
                  break;
               case 'subject':
                  $this->set_subject( $v );
                  break;
               case 'mainbody':
               case 'message':      // Depricated
                  $this->set_mainbody( $v );
                  break;
               case 'parts':
                  $this->parts = is_array( $v ) ? $v : array();;
                  break;
               case 'headers':
                  $this->headers = is_string( $v ) ? $v : '';
                  break;
               default:
                  die( "Bad parameter <b>$k</b> to \$$classname\->addspec().<br>\n" );
                  // break;
            }
         }
         return 0;
      }


      // 
      // add_to -- add a To: recipient to message parameters
      // 
      // E-mail address is default Real Name.
      // 
      function add_to( $addr, $name='' )
      {
         if ( is_array( $addr ) )
         {
            reset( $addr );
            while( list( $a, $n ) = each( $addr ) )
            {
               $a = $this->chk_addr( $a );
               $n = $this->chk_name( $n );
               if (! $a ) return false;
               if ( ! $n ) $n = $a;
               $this->to[ $a ] = $n;
            }
         }

         if ( is_string( $addr ) )
         {
            $addr = $this->chk_addr( $addr );
            $name = $this->chk_name( $name );
            if ( ! $addr ) return false;
            if ( ! $name ) $name = $addr;
            $this->to[ $addr ] = $name;
         }
         return true;
      }


      // 
      // add_cc -- add a Cc: recipient to message parameters
      // 
      // E-mail address is default Real Name.
      // 
      function add_cc( $addr, $name='' )
      {
         if ( is_array( $addr ) )
         {
            reset( $addr );
            while( list( $a, $n ) = each( $addr ) )
            {
               $a = $this->chk_addr( $a );
               $n = $this->chk_name( $n );
               if ( ! $a ) return false;
               if ( ! $n ) $n = $a;
               $this->cc[ $a ] = $n;
            }
         }

         if ( is_string( $addr ) )
         {
            $addr = $this->chk_addr( $addr );
            $name = $this->chk_name( $name );
            if ( ! $addr ) return false;
            if ( ! $name ) $name = $addr;
            $this->cc[ $addr ] = $name;
         }
         return true;
      }


      // 
      // add_bcc -- add a Bcc: recipient to message parameters
      // 
      // The real name in $name is saved but never used.
      // 
      function add_bcc( $addr, $name='' )
      {
         if ( is_array( $addr ) )
         {
            reset( $addr );
            while( list( $a, $n ) = each( $addr ) )
            {
               $a = $this->chk_addr( $a );
               if ( ! $a ) return false;
               $this->bcc[ $a ] = $n;
            }
         }

         if ( is_string( $addr ) )
         {
            $addr = $this->chk_addr( $addr );
            if ( ! $addr ) return false;
            $this->bcc[ $addr ] = $name;
         }
         return true;
      }


      // 
      // set_from -- set the message sender
      // 
      // E-mail address is default Real Name.
      // 
      function set_from( $addr, $name='' )
      {
         $addr = $this->chk_addr( $addr );
         $name = $this->chk_name( $name );
         if ( ! $addr ) return false;
         if ( ! $name ) $name = $addr;
         $this->from = array( $addr => $name );
         return true;
      }


      // 
      // set_replyto -- set the message reply to information
      // 
      // E-mail address is default Real Name.
      // 
      function set_replyto( $addr, $name='' )
      {
         $addr = $this->chk_addr( $addr );
         $name = $this->chk_name( $name );
         if ( ! $addr ) return false;
         if ( ! $name ) $name = $addr;
         $this->replyto = array( $addr => $name );
         return true;
      }


      // 
      // set_subject -- set the message subject
      // 
      function set_subject( $subj='' )
      {
         $this->subject = is_string( $subj ) ? $subj : '';
         return true;
      }


      // 
      // set_mainbody -- set the main body of message
      // 
      function set_mainbody( $mb='' )
      {
         $this->mainbody = is_string( $mb ) ? $mb : '';
         return true;
      }


      //
      //
      function mk_datestamp( $datestamp = '' ) {
         $ds = $datestamp;
         if ( ! $ds ) {
            $gtod = gettimeofday();
            $now = $gtod[ 'sec' ];
            $west = $gtod[ 'minuteswest' ];
            $zone = sprintf( '%s%04d', $west <= 0 ? '+' : '-', abs($west)/.6 );
            $ds = strftime( "%a, %d %b %Y %T $zone (%Z)", $now );
         }
         return $ds;
      }

      // 
      // mail_separate -- send the e-mail to everyone separately.
      // 
      function mail_separate( $specarray = false )
      {
         $ret = 0;

         if ( $specarray ) {
            $ret = $this->setspec( $specarray );
         }

         if ( $ret == 0 ) {
            $ret = $this->mk_allparts();
         }

         $to = $this->to;
         $cc = $this->cc;
         $bcc = $this->bcc;
         $accum_fail = '';
         $accum_to = '';
         $accum_cc = '';
         $accum_bcc = '';

         if ( $ret == 0 ) {
            $datestamp = $this->mk_datestamp( $datestamp );
   
            reset( $to );
            while ( list( $k, $v ) = each( $to ) ) {
               $tmp = $this->mail( array( 'to' => array( $k => $v ) ), $datestamp );
               if (  $tmp == 0 ) {
                  $accum_to .= $accum_to ? "\n$this->last_to" : $this->last_to;
               }
               else {
                  $tempaddr = $this->mk_addrname( $k, $v );
                  $accum_fail .= $accum_fail ? "\n$tempaddr" : $tempaddr;
                  $ret = $tmp;
               }
            }
            $this->last_to = $accum_to;
   
            reset( $cc );
            while ( list( $k, $v ) = each( $cc ) ) {
               $tmp = $this->mail( array( 'cc' => array( $k => $v ) ), $datestamp );
               if (  $tmp == 0 ) {
                  $accum_cc .= $accum_cc ? "\n$this->last_cc" : $this->last_cc;
               }
               else {
                  $tempaddr = $this->mk_addrname( $k, $v );
                  $accum_fail .= $accum_fail ? "\n$tempaddr" : $tempaddr;
                  $ret = $tmp;
               }
            }
            $this->last_cc = $accum_cc;
   
            reset( $bcc );
            while ( list( $k, $v ) = each( $bcc ) ) {
               $tmp = $this->mail( array( 'bcc' => array( $k => $v ) ), $datestamp );
               if (  $tmp == 0 ) {
                  $accum_bcc .= $accum_bcc ? "\n$this->last_bcc" : $this->last_bcc;
               }
               else {
                  $tempaddr = $this->mk_addrname( $k, $v );
                  $accum_fail .= $accum_fail ? "\n$tempaddr" : $tempaddr;
                  $ret = $tmp;
               }
            }
            $this->last_bcc = $accum_bcc;
            $this->last_fail = $accum_fail;
         }

         $this->partsaremade = false;
         return $ret;
      }


      // 
      // mail -- send the e-mail to everyone separately.
      // 
      function mail( $specarray = false, $datestamp = '' )
      {

         $fromname = '';
         $fromaddr = '';
         $fromhead = '';
         $replytoname = '';
         $replytoaddr = '';
         $replytohead = '';
         $to    = '';
         $cc    = '';
         $bcc   = '';
         $temp  = 0;
         $ret   = 0;

         $addr  = '';
         $addr_to = '';
         $addr_cc = '';
         $addr_bcc = '';
         $addr_from = '';
         $addr_replyto = '';

         $mime_headers = '';
         $mainpart_head = '';
         $mainpart_tail = '';
         $attachments = '';

         $this->last_boundry = '';
         $this->last_parts = array();
         $this->last_to       = '';
         $this->last_cc       = '';
         $this->last_bcc      = '';
         $this->last_from     = '';
         $this->last_replyto  = '';
         $this->last_date     = '';
         $this->last_subject  = '';
         $this->last_message  = '';
         $this->last_result  = '';

         // Init message specification.
         if ( $specarray ) {
            $ret = $this->setspec( $specarray );
         }

         // Init hmailer exe file location and name.
         if ( $ret == 0 ) {
            if ( ! $this->exefile ) {
               $this->exefile = getenv( "HMAILER" );
               if ( $this->exefile ) $this->exefile .= '\HMailer.exe';
            }
            if ( ! $this->exefile ) $ret = -5;
         }

         // Init temp directory
         if ( $ret == 0 ) {
            if ( ! $this->tmpdir ) $this->tmpdir = getenv( "TMPDIR" );
            if ( ! $this->tmpdir ) $this->tmpdir = getenv( "TMP" );
            if ( ! $this->tmpdir ) $this->tmpdir = getenv( "TEMP" );
            if ( ! $this->tmpdir  ) $ret = -6;
         }

         // Ensure non-empty from and mainbody
         if ( ($ret == 0) && ! $this->mainbody ) $ret = -7;
         if ( ($ret == 0) && ! count( $this->from ) ) $ret = -8;

         // Ensure at least on recipient
         if ( $ret == 0 ) {
            $temp = count( $this->to)  +
                    count( $this->cc ) + count( $this->bcc );
            if ( $temp == 0 ) $ret = -9;
         }

         // Prepare To: recipients
         $comma = '';
         reset( $this->to );
         while ( list( $k, $v ) = each( $this->to ) ) {
            $temp     = $this->mk_addrname( $k, $v );
            $to      .= $comma . $temp;
            $addr    .= "$k\r\n";
            $this->last_to  .= "$temp\n";
            $comma    = ", \\\r\n    ";
         }
         $this->last_to = chop( $this->last_to );

         // Prepare Cc: recipients
         $comma = '';
         reset( $this->cc );
         while ( list( $k, $v ) = each( $this->cc ) ) {
            $temp    = $this->mk_addrname( $k, $v );
            $cc     .= $comma . $temp;
            $addr   .= "$k\r\n";
            $this->last_cc .= "$temp\n";
            $comma   = ", \\\r\n    ";
         }
         $this->last_cc = chop( $this->last_cc );

         // Prepare Bcc: recipients
         $comma = '';
         reset( $this->bcc );
         while ( list( $k, $v ) = each( $this->bcc ) ) {
            $temp     = $this->mk_addrname( $k, $v );
            $bcc     .= $comma . $temp;
            $addr    .= "$k\r\n";
            $this->last_bcc .= "$temp\n";
            $comma    = ", \\\r\n    ";
         }
         $this->last_bcc = chop( $this->last_bcc );

         // Prepare From: address
         reset( $this->from );
         while ( list( $k, $v ) = each( $this->from ) ) {
            $fromaddr = $k;
            $fromname = $v;
         }
         $this->last_from = $this->mk_addrname( $fromaddr, $fromname );

         // Prepare Replyto: address
         reset( $this->replyto );
         while ( list( $k, $v ) = each( $this->replyto ) ) {
            $replytoaddr = $k;
            $replytoname = $v;
            $this->last_replyto = $this->mk_addrname( $k, $v );
         }

         // Prepare Subject:
         $this->last_subject =
            $this->subject ? $this->subject : $this->default_subject;

         // Prepare mainbody
         $this->mainbody = preg_replace( '/\r?\n/', "\r\n", $this->mainbody );
         $this->mainbody = chop( $this->mainbody ) . "\r\n";

         // Prepare attachments
         if ( count( $this->parts ) > 0 ) {
            if ( ! $this->partsaremade ) {
               $ret = $this->mk_allparts();
            }
            if ( $ret == 0) {
               $boundary = uniqid( '_=_=_MIME.PART.BOUNDARY.' ) . '=_=_=_';
               $mime_headers .= "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
               $mainpart_head = "--$boundary\r\nContent-Type: text/plain; charset=\"us-ascii\"\r\n\r\n";
               $mainpart_tail = "\r\n--$boundary\r\n";
               reset( $this->parts );
               while ( list( $k ) = each( $this->parts ) ) {
                  $v = &$this->parts[ $k ];
                  if ( $attachments ) {
                     $attachments .=  "\r\n" . $v[ '_headers' ] . $v[ '_body' ] . "\r\n--$boundary";
                  }
                  else {
                     $attachments .=  $v[ '_headers' ] . $v[ '_body' ] . "\r\n--$boundary";
                  }
               }
               $attachments .= "--\r\n";
            }
         }
         
         // Prepare date stamp
         $datestamp = $this->mk_datestamp( $datestamp );

         // Build full message
         if ( $to ) {
            $this->last_message  .=  "To: $to\r\n";
         }
         else if ( ! $cc ) {
            $this->last_message  .=  "To: $this->default_to\r\n";
         }
         if ( $cc )
            $this->last_message  .=  "Cc: $cc\r\n";
         if ( $this->last_from )
            $this->last_message  .=  "From: $this->last_from\r\n";
         if ( $this->last_replyto )
            $this->last_message  .=  "Reply-To: $this->last_replyto\r\n";
         if ( $this->last_subject )
            $this->last_message  .=  "Subject: $this->last_subject\r\n";
         $this->last_message  .=  "Date: $datestamp\r\n";
         $this->last_message  .=  $this->headers . $mime_headers;
         $this->last_message  .=  "\r\n";
         $this->last_message  .=  $mainpart_head;
         $this->last_message  .=  $this->mainbody;
         $this->last_message  .=  $mainpart_tail;
         $this->last_message  .=  $attachments;

         // Open temp files: recipient addresses file
         if ( $ret == 0 ) {
            $addrname = @tempnam( $this->tmpdir, 'hma.' . uniqid( mt_rand(), true ) . '.' );
            $addrname = ereg_replace( "\/", "\\", $addrname );
            $addrfile = @fopen( $addrname, 'w' );
            if ( ! $addrfile ) $ret = -10;
         }

         // Open temp files: message file
         if ( $ret == 0 ) {
            $msgname = @tempnam( $this->tmpdir, 'hmm.' . uniqid( mt_rand(), true ) . '.' );
            $msgname = ereg_replace( "\/", "\\", $msgname );
            $msgfile = @fopen( $msgname, 'w' );
            if ( ! $msgfile )
            {
               @fclose( $addrfile );
               @unlink( $addrname );
               $ret = -11;
            }
         }

         // Send message if all okay till now.
         if ( $ret == 0 ) {
            // Write temp files
            @fwrite( $addrfile, $addr );
            @fclose( $addrfile );
            @fwrite( $msgfile, $this->last_message );
            @fclose( $msgfile );

            // Form command line
            $cmdline = sprintf( '%s "%s" "%s" "%s" 1>nul 2>nul',
                                $this->exefile,
                                $fromaddr, $addrname, $msgname );

            // Execute command line
            @system( $cmdline, $ret );

            // Delete temporory files
            if ( file_exists( $addrname ) ) @unlink( $addrname );
            if ( file_exists( $msgname  ) ) @unlink( $msgname  );

         }

         // Finish up
         $this->last_result = $ret;
         return $ret;
      } 
   }

?>
