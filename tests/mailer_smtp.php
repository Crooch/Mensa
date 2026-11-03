<?php
declare(strict_types=1);
// SMTP fittizio solo su loopback: nessuna email lascia il processo di test.
if (($argv[1] ?? '') === 'server') {
    $server=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
    if (!$server) exit(2);
    echo stream_socket_get_name($server,false)."\n";flush();
    $c=stream_socket_accept($server,15);if(!$c)exit(3);stream_set_timeout($c,15);
    fwrite($c,"220 local test\r\n");$auth=0;$data=false;$capture=['commands'=>[],'body'=>''];
    while(($line=fgets($c))!==false){
        $cmd=rtrim($line,"\r\n");
        if($data){if($cmd==='.'){$data=false;fwrite($c,"250 queued\r\n");}else{$capture['body'].=$line;}continue;}
        $capture['commands'][]=$cmd;
        if($auth){$auth++;fwrite($c,$auth===2?"334 password\r\n":(($argv[3]??'')==='fail'?"535 secret-must-not-leak\r\n":"235 accepted\r\n"));if($auth===3)$auth=0;continue;}
        if(str_starts_with($cmd,'EHLO'))fwrite($c,"250-local\r\n250 AUTH LOGIN\r\n");
        elseif($cmd==='AUTH LOGIN'){$auth=1;fwrite($c,"334 username\r\n");}
        elseif($cmd==='DATA'){$data=true;fwrite($c,"354 continue\r\n");}
        elseif($cmd==='QUIT'){fwrite($c,"221 bye\r\n");break;}
        else fwrite($c,"250 accepted\r\n");
    }
    file_put_contents($argv[2],json_encode($capture));fclose($c);fclose($server);exit;
}
require dirname(__DIR__).'/bootstrap.php';
use App\Core\Config;
use App\Core\Mailer;
use App\Service\ImpostazioniEmail;
Config::inizializza(['db'=>['database'=>'mailer_unit_no_database']]);
function check(bool $v,string $message):void {if(!$v)throw new RuntimeException($message);echo "PASS $message\n";}
function smtpTest(string $mode):array {
    $file=tempnam(sys_get_temp_dir(),'smtp-unit-');$pipes=[];
    $proc=proc_open([PHP_BINARY,__FILE__,'server',$file,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    try{
        stream_set_timeout($pipes[1],15);$address=trim((string)fgets($pipes[1]));
        if(!preg_match('/^127\.0\.0\.1:(\d+)$/',$address,$m))throw new RuntimeException('Local SMTP fixture unavailable');
        $mailer=new Mailer(['host'=>'127.0.0.1','port'=>(int)$m[1],'security'=>'none','username'=>'unit-user','password'=>'unit-secret','from'=>'sender@example.invalid']);
        if($mode==='send')$mailer->invia(['test@example.invalid'],[],'[PROVA] Mensa à',".inizio\nCaffè e quantità: 12\n.\nfine");
        else $mailer->verifica();
    }catch(RuntimeException $e){if($mode!=='fail')throw $e;check(str_contains($e->getMessage(),'Autenticazione')&&!str_contains($e->getMessage(),'secret'),'authentication failure surfaced without remote secret');}
    finally{foreach($pipes as $p)fclose($p);$status=proc_close($proc);}
    $capture=json_decode(file_get_contents($file),true);unlink($file);check($status===0,'local SMTP session terminated');return $capture;
}
$r=smtpTest('verify');check(count(array_filter($r['commands'],fn($c)=>str_starts_with($c,'RCPT')||$c==='DATA'))===0,'verification authenticates without sending');
$r=smtpTest('send');check(array_values(array_filter($r['commands'],fn($c)=>str_starts_with($c,'RCPT')))===['RCPT TO:<test@example.invalid>'],'one explicit test recipient, no copies');
[$headers,$body]=explode("\r\n\r\n",$r['body'],2);check(!str_contains($headers,'Cc:')&&str_contains($headers,'Content-Transfer-Encoding: base64'),'MIME headers without CC');
check(base64_decode($body,true)===".inizio\nCaffè e quantità: 12\n.\nfine",'UTF-8 multiline and dot lines preserved');smtpTest('fail');
Config::inizializza(['app'=>['solleciti_automatici_enabled'=>true]]);check(ImpostazioniEmail::automatici([])&&!ImpostazioniEmail::automatici(['solleciti_automatici_enabled'=>'0']),'Admin disable overrides legacy true');
Config::inizializza(['app'=>['solleciti_automatici_enabled'=>false]]);check(!ImpostazioniEmail::automatici([])&&ImpostazioniEmail::automatici(['solleciti_automatici_enabled'=>'1']),'Admin enable overrides legacy false, default remains off');
