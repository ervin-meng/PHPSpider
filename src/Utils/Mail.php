<?php
namespace PHPSpider\Utils;

use PHPMailer;

class Mail
{
    static public function send($to,$subject,$body,$attachment='',$from='',$fromName='')
    {
        if(empty($to)||empty($subject)||empty($body))
        {
            return false;
        }

        $to = is_array($to)?$to:array($to);
        $body = eregi_replace("[\]",'',$body);
        $config = C('EMAIL.SMTP');

        $mail = new PHPMailer;
        $mail->IsSMTP();
        $mail->SMTPAuth   = $config['auth'];
        $mail->SMTPSecure = "";
        $mail->CharSet    = $config['charset'];
        $mail->Host       = $config['server'];
        $mail->Port       = $config['port'];
        $mail->Username   = $config['account'];
        $mail->Password   = $config['password'];
        $mail->From       = empty($from)?$config['from']:$from;
        $mail->FromName   = empty($fromName)?$config['fromName']:$fromName;
        $mail->Subject    = empty($subject)?$config['subject']:$subject;
        $mail->WordWrap   = 50;
        #$mail->MsgHTML($body);
        $mail->AddReplyTo($mail->From ,$mail->FromName );
        $mail->IsHTML(true);
        $mail->Body = $body;

        if(!empty($attachment) && file_exists($attachment))
        {
            $mail->AddAttachment($attachment,basename($attachment));
        }

        foreach($to as $v)
        {
            $mail->AddAddress($v);
        }

        return $mail->Send();
    }
}