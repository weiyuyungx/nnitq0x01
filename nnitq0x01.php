<?php
ini_set('memory_limit','1024M');


$base_dir = '/';

$GLOBALS['begin_time'] = getmsectime();  //开女台时间
$GLOBALS['file_num'] = 0;   //文件数量
$GLOBALS['empty_dir'] = 0;  //空文件夹数量
$GLOBALS['runing_mission'] = 0;  //正在执行的任务数量,用来判断结束


$chan = new \Swoole\Coroutine\Channel(1000000);  //文件夹列表的channel

//开始执行
runmission($base_dir,$chan);

//监听channel的数据
go(function() use ($chan){
    
    while(true)
    {
        $data = $chan->pop();
        
        $GLOBALS['runing_mission'] --;  //完成一个任务
        
        $list = $data['list'];
        $dir = $data['dir'];
        
        $GLOBALS['file_num'] += $data['file_num'];   //文件数量
        $GLOBALS['empty_dir'] += $data['empty_dir'];  //空文件夹数量
        
 
        if ($list !== false)
        {
            //有料的文件夹
            foreach ($list as $one)
            {
                if ($one == '.' || $one == '..')
                {
                    continue;  //拍黄片专属
                }
                
                $next_dir = $dir.'/'.$one;
                
                runmission($next_dir,$chan);
            }
        }
        

      //  echo 'runing_mission: '.$GLOBALS['runing_mission'].PHP_EOL;
      //  echo 'file_num:'.$GLOBALS['file_num'] .PHP_EOL;
        
        
        //判断是否结束
        if ($GLOBALS['runing_mission'] <= 0)
        {
            $useing_time = getmsectime() - $GLOBALS['begin_time'] ; //单位毫秒
            
            echo 'end---------------------------------------------------'.date('H:i:s').PHP_EOL;
            echo 'file_num:'.$GLOBALS['file_num'] .PHP_EOL;
            echo 'empty_dir:'.$GLOBALS['empty_dir'] .PHP_EOL;
            
            echo 'useing_time:'.number_format($useing_time / 1000 , 3).' (s)'.PHP_EOL;
            echo 'speed:'.(int)(($GLOBALS['file_num'] +$GLOBALS['empty_dir'] )*1000/$useing_time).' (f/s)'.PHP_EOL;
        }
    }
    
});
    
    
    //毫秒的时间戳
    function getmsectime()
    {
        list ($msec, $sec) = explode(' ', microtime());
        $msectime = (float) sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
        return $msectime;
    }
    
    //开启一个任务(读目录列表)
    function runmission($dir,$chan)
    {
        $GLOBALS['runing_mission'] ++;  //开启一个任务
        
        go(function() use ($dir,$chan){
            
            $data['file_num'] = 0;
            $data['empty_dir'] = 0;
            $data['dir'] = $dir;
            $data['list'] = false;
            
            if (is_file($dir))
            {
                //目录连接当成一个文件
                $data['file_num'] ++;
            }
            elseif (is_link ($dir))
            {
                $data['file_num'] ++;
            }
            else
            {
                $list = @scandir($dir);
                
                //判断文件类型
                if ($list === false)
                {
                    //文件或者 不存在的目录
                    $data['file_num'] ++; 
                }
                elseif (count($list) <= 2)
                {
                    //空目录
                    $data['empty_dir'] ++;
                }
                else
                {
                    //有货的目录
                    $data['dir'] = $dir;
                    $data['list'] = $list;  
                }
            }
            
            $chan->push($data);
        });
    }
    
    
    
    