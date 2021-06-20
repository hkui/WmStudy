<?php
//https://segmentfault.com/a/1190000018643894

$splPriorityQueue = new \SplPriorityQueue();
// 设定返回数据的meta信息
// \SplPriorityQueue::EXTR_DATA 默认 只返回数
// \SplPriorityQueue::EXTR_PRIORITY 只返回优先级
// \SplPriorityQueue::EXTR_BOTH 返回数据和优先级
// $splPriorityQueue->setExtractFlags(\SplPriorityQueue::EXTR_DATA);
$splPriorityQueue->insert("task1", 1);
$splPriorityQueue->insert("task2", 1);
$splPriorityQueue->insert("task3", 1);
$splPriorityQueue->insert("task4", 1);
$splPriorityQueue->insert("task5", 1);

echo $splPriorityQueue->extract() . PHP_EOL;
echo $splPriorityQueue->extract() . PHP_EOL;
echo $splPriorityQueue->extract() . PHP_EOL;
echo $splPriorityQueue->extract() . PHP_EOL;
echo $splPriorityQueue->extract() . PHP_EOL;

