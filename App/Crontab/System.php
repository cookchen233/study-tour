<?php

namespace App\Crontab;


use App\Utility\exception\AppException;
use App\Utility\hilog\HiLogger;
use Hisune\EchartsPHP\ECharts;
use One\Facades\Redis;
use PHPMailer\PHPMailer\PHPMailer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class System
{
    /**
     * @condition
     * @return string
     */
    public static function xx(){}

    public static function yy(){}

    public static function collectMemoryUsed(){
        Redis::lpush('memory_used_'.date('Ymd'), json_encode(['value' => [date('Y-m-d H:i'), memory_get_usage()]]));
    }

    public static function sendMemoryUsedMail(){
        /*$data = cache('memory_used_'.date('Y-m-d'));
        $chart = new ECharts();
        $chart->setOption([
            "tooltip" => [
                "trigger" => "axis"
            ],
            "xAxis" => [
                "type" => "time",
                "interval" => 1000*3600,
                "min" => date("Y-m-d 00:00"),
                "max" => date("Y-m-d 23:59"),
            ],
            "yAxis" => [
                "type" => "value",
                "splitLine" => [
                    "show"=> false
                ]
            ],
            "series" => [
                "type" => "line",
                "data"=> $data,
            ]
        ]);
        return $chart->render('x');*/

        /*$content = [];
        foreach (cache('memory_used_'.date('Y-m-d')) as $v){
            $memory = number_format($v['value'][1]);
            $content[] = <<<eot
<tr><td width="160">{$v['value'][0]}</td><td>{$memory}</td></tr>
eot;
        }
        $content = '<table style="border-collapse: collapse;">'. implode('', $content) .'</table>';*/

        /*foreach (cache('memory_used_'.date('Y-m-d')) as $k => $v){
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A'. ($k+1), $v['value'][0]);
            $sheet->setCellValue('B'. ($k+1), number_format($v['value'][1]));
            $writer = new Xlsx($spreadsheet);
            $writer->save(_APP_PATH_.'/RunCache/memory_used.xlsx');
        }*/

        $filename = _APP_PATH_.'/RunCache/log/temp/memory_used.log';
        if(file_exists($filename)){
            unlink($filename);
        }
        mkfile($filename);
        $file_num  = 0;
        $fp=fopen($filename,'a');
        $attachs = [$filename];
        while(1){
            $v = Redis::lpop('memory_used_'.date('Ymd'));
            if(!$v){
                break;
            }
            $v = json_decode($v, true);
            if(filesize($filename) > 1024*1024*2){
                $file_num++;
                $filename = str_replace('.log',  "_$file_num.log", $filename);
                $attachs[] = $filename;
                if(file_exists($filename)){
                    unlink($filename);
                }
                fclose($fp);
                $fp=fopen($filename,'a');
            }
            fwrite($fp, PHP_EOL . $v['value'][0] .' '. number_format($v['value'][1]));
        }
        fclose($fp);

        $config = config('mail');
        $mail = new PHPMailer();
        $mail->IsSMTP();                 // 设置使用 SMTP
        $mail->SMTPAuth = true;        // 设置为安全验证方式
        //$mail->SMTPDebug  = 4;
        $mail->Timeout  = 20;
        $mail->Host = $config['host'];          // 指定的 SMTP 服务器地址
        //$mail->Host = 'ssl://'. $config['host'] .':465';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;
        $mail->Username = $config['username'];             // SMTP 发邮件人的用户名
        $mail->Password = $config['password'];             // SMTP 密码
        $mail->CharSet = $mail::CHARSET_UTF8;
        $mail->From = $config['address'];
        $mail->FromName = $config['name'];
        $mail->Subject = sprintf('内存使用报告-%s(%s-%s-%s)', date('Y-m-d'), php_uname('s'), php_uname('n'), php_uname('r'));// 标题
        //$mail->SingleTo = true; //单发
        foreach(config('mail.dev_group_address') as $v){
            $mail->AddAddress($v['address'], $v['name']);
        }
        foreach ($attachs as $v){
            $mail->AddAttachment($v, pathinfo($v, PATHINFO_BASENAME));  // 附件，也可选加命名附件
        }
        //$mail->WordWrap = 50;                 // set word wrap to 50 characters
        //$mail->IsHTML(true);                  // 设置邮件格式为 HTML
        //$mail->AltBody = "This is the body in plain text for non-HTML mail clients"; // 附加内容
        //$mail->Body  = $content;
        $mail->Body  = date('Y-m-d'). ' 当日内存使用量, 见附件';
        if (!$mail->Send()) {
            \One\Facades\Log::alert($mail->ErrorInfo);
            throw new AppException('邮件发送失败,' . $mail->ErrorInfo);
        }
    }
}