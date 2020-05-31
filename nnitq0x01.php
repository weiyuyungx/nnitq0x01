<?php 
ini_set('memory_limit','1024M'); 


$base_dir = '/etc';

$GLOBALS['begin_time'] = getmsectime();  //开女台时间
$GLOBALS['file_num'] = 0;   //文件数量
$GLOBALS['empty_dir'] = 0;  //空文件夹数量
$GLOBALS['runing_mission'] = 0;  //正在执行的任务数量,用来判断结束

//Co::set(['hook_flags'=> SWOOLE_HOOK_ALL]);

$chan = new Swoole\Coroutine\Channel(1000);  //文件夹列表的channel

//开始执行
$GLOBALS['runing_mission'] ++;
runmission($base_dir,$chan);


//监听channel的数据
go(function() use ($chan){
    
    while(true) 
    {
        $data = $chan->pop();
        
        $GLOBALS['runing_mission'] --; //完成一个任务
        
        $list = $data['list'];
        $dir = $data['dir'];
        
        
        if (count($list) == 2)
        {
            //空文件夹
            $GLOBALS['empty_dir'] ++;
        }
        else 
        {
            //有料的文件夹
            foreach ($list as $k=>$one)
            {
                if ($one == '.' || $one == '..')
                {
                    continue;  //拍黄片专属
                }

                $GLOBALS['runing_mission'] ++ ;
                //去协程去判断是不是文件 
                
                $next_dir = $dir.'/'.$one;
                go(function() use ($next_dir,$chan){
                    
                    if (is_file($next_dir))
                    {
                        $GLOBALS['file_num'] ++ ;
                        $GLOBALS['runing_mission'] --;  //完成一个任务
                    }
                    else
                    {
                        //如果是文件夹。开启一个任务
                        runmission($next_dir,$chan);
                    }
                });
            }
        }

        //判断是否结束
        if ($GLOBALS['runing_mission'] == 0)
        {
            $useing_time = getmsectime() - $GLOBALS['begin_time'] ; //单位毫秒
            
            echo 'end........'.date('H:i:s').PHP_EOL;
            echo 'file_num:'.$GLOBALS['file_num'] .PHP_EOL;
            echo 'empty_dir:'.$GLOBALS['empty_dir'] .PHP_EOL;

            echo 'useing_time:'.number_format($useing_time / 1000 , 3).'(s)'.PHP_EOL;
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
    go(function() use ($dir,$chan){
        
        $list = @scandir($dir);
        
        $data['dir'] = $dir;
        $data['list'] = $list;
        
        $chan->push($data);
    });
}



