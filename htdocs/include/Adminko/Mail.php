<?php
namespace Adminko;

include_once CLASS_DIR . 'swiftmailer/lib/swift_required.php';

class Mail
{
    public static function send($to, $from, $name, $subject, $body, $files = array())
    {
        return self::sendMessage($to, self::prepareMessage($from, $name, $subject, $body, $files));
    }

    public static function prepareMessage($from, $name, $subject, $body, $files = array())
    {
        $message = \Swift_Message::newInstance();

        $body = preg_replace_callback( 
            '/src=\"(.+)\"/isU', 
            function($match) use ($message) {
                $path_parts = pathinfo($match[1]);
                $filename = $path_parts['basename'];
                $mime_type = 'image/' . strtolower($path_parts['extension']);

                if ($img_data = file_get_contents($match[1])) {
                    $cid = $message->embed(\Swift_Image::newInstance($img_data, $filename, $mime_type));
                } else {
                    $cid = $match[1];
                }
                return 'src="' . $cid . '"';
            }, $body 
        ); 

        $message
            ->setSubject($subject)
            ->setFrom(array($from => $name))
            ->setBody($body, 'text/html');

        foreach ($files as $file_name => $file_path) {
            $message->attach(\Swift_Attachment::fromPath($file_path));
        }
        
        return $message;
    }
    
    public static function sendMessage($to, $message)
    {
        $message->setTo($to);
        
        $transport = \Swift_MailTransport::newInstance();
        
        $mailer = \Swift_Mailer::newInstance($transport);
        
        return $result = $mailer->send($message);
    }
}
