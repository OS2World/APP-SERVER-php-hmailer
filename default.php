<?php

   include( 'hmailer.php' );

   class Local_HMailer extends HMailer {
      var $tmpdir    = '';
      var $exefile   = 'F:\\HMailer\\bin\\HMailer.exe';
      var $default_subject = '(no subject)';
   }


   global $visitor_name, $visitor_email, $visitor_comment, $submitbtn;
   global $errormsg;


   // Initialize.
   $errormsg = '';

   // First time through.
   if ( ! $submitbtn ) {
      $vistor_name = '';
      $vistor_email = '';
      $vistor_comment = '';
      include( 'sample_form.ihtml' );
      exit;
   }

   // Validate: non-empty comment.
   if ( ! $visitor_comment  ) {
      $errormsg .= <<<EOVerrormsg
 <p><font color="red" size="+1"><b>Problem in submitted data</b></font><br>
  <font color="red">No comment was entered.  We can not send our webmaster
  an empty message.<br></font>
 </p>
EOVerrormsg;
   }

   // Validate: e-mail address.
   if ( $visitor_email ) {
      if ( ! preg_match( 
           '/^[-!#$%&\'*+\\.\/0-9=?A-Z^_`{|}~]+@([-0-9A-Z]+\.)+((([0-9A-Z]){2,3})|(arpa))$/i' ,
           trim( $visitor_email ) ) ) {
      $errormsg .= <<<EOVerrormsg
You entered an invalid e-mail address.  We can not send a reply without
a valid e-mail address.  If you do not wish to receive a reply, please
leave the e-mail address field blank.<br>
EOVerrormsg;
      }
   }

   // Validation failed.
   if ( $errormsg != '' ) {
      include( 'sample_form.ihtml' );
      exit;
   }


   // Validation succeeded.
   $msg = <<<EOVmsg
This IMECAT website Feedback Report was generated automatically.

Description:
  A website visitor has submitted the feedback.

User Comment:
========================= Begin User Comment =========================
$visitor_comment
=========================  End User Comment  =========================

Additional form information:
      Visitor's name: $visitor_name
    Visitor's e-mail: $visitor_email
EOVmsg;


   $lhm = new Local_HMailer;
   $lhm->setspec();
   $lhm->mail( array( 
       'to'      => array( 'feedback@sample.com' => 'Feedback Coordinator' ),
       'from'    => array( 'webmaster@sample.com' => 'Website Feedback' ),
       'replyto' => array( $visitor_email => $visitor_name ),
       'subject' => 'Website feedback', 
       'message' => $msg
                   )      );

   include( 'sample_thankyou.ihtml' );
   exit;

?>
