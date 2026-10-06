<?php
declare(strict_types=1);

namespace DigiSangam\Notifications;

final class SmtpEmailProvider implements EmailProviderInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {}

    public function send(string $to,string $subject,string $html,string $text=''): array
    {
        if(!filter_var($to,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Invalid email recipient.');
        if($this->host===''||$this->fromAddress==='') throw new \RuntimeException('SMTP is not configured.');

        $transport=$this->encryption==='ssl'?'ssl://'.$this->host:$this->host;
        $socket=@stream_socket_client($transport.':'.$this->port,$errno,$errstr,15,STREAM_CLIENT_CONNECT);
        if(!$socket) throw new \RuntimeException('SMTP connection failed.');
        stream_set_timeout($socket,20);

        try{
            $this->expect($socket,[220]);
            $this->command($socket,'EHLO digisangam.local',[250]);
            if($this->encryption==='tls'){
                $this->command($socket,'STARTTLS',[220]);
                if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new \RuntimeException('SMTP TLS negotiation failed.');
                $this->command($socket,'EHLO digisangam.local',[250]);
            }
            if($this->username!==''){
                $this->command($socket,'AUTH LOGIN',[334]);
                $this->command($socket,base64_encode($this->username),[334]);
                $this->command($socket,base64_encode($this->password),[235]);
            }
            $this->command($socket,'MAIL FROM:<'.$this->fromAddress.'>',[250]);
            $this->command($socket,'RCPT TO:<'.$to.'>',[250,251]);
            $this->command($socket,'DATA',[354]);

            $boundary='ds_'.bin2hex(random_bytes(8));
            $message=[
                'From: '.$this->encodeHeader($this->fromName).' <'.$this->fromAddress.'>',
                'To: <'.$to.'>',
                'Subject: '.$this->encodeHeader($subject),
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="'.$boundary.'"',
                '',
                '--'.$boundary,
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                '',
                $text!==''?$text:strip_tags($html),
                '--'.$boundary,
                'Content-Type: text/html; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
                '',
                $html,
                '--'.$boundary.'--',
            ];
            $body=implode("\r\n",$message);
            $body=preg_replace('/(?m)^\./','..',$body) ?? $body;
            fwrite($socket,$body."\r\n.\r\n");
            $this->expect($socket,[250]);
            $this->command($socket,'QUIT',[221]);
        }finally{
            fclose($socket);
        }

        return ['provider'=>'smtp','id'=>'smtp_'.bin2hex(random_bytes(5))];
    }

    private function command($socket,string $command,array $codes): void
    {
        fwrite($socket,$command."\r\n");
        $this->expect($socket,$codes);
    }

    private function expect($socket,array $codes): void
    {
        $response='';
        while(($line=fgets($socket,515))!==false){
            $response.=$line;
            if(strlen($line)>=4 && $line[3]===' ') break;
        }
        $code=(int)substr($response,0,3);
        if(!in_array($code,$codes,true)) throw new \RuntimeException('SMTP server rejected request: '.$code);
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?'.base64_encode($value).'?=';
    }
}
