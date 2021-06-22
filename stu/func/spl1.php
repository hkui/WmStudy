<?php
$splPriorityQueue = new \SplPriorityQueue();

$splPriorityQueue->insert("task1", 1);
$splPriorityQueue->insert("task2", 2);
$splPriorityQueue->insert("task3", 1);
$splPriorityQueue->insert("task4", 4);
$splPriorityQueue->insert("task5", 5);

print_r($splPriorityQueue);
echo "Countable： " . count($splPriorityQueue) . PHP_EOL;

// 迭代的话会删除队列元素 current 指针始终指向 top 所以 rewind 没什么意义
for ($splPriorityQueue->rewind(); $splPriorityQueue->valid();$splPriorityQueue->next()) {
    var_dump($splPriorityQueue->current());
    var_dump($splPriorityQueue->count());
    $splPriorityQueue->rewind();
}

var_dump("is empty:" . $splPriorityQueue->isEmpty());