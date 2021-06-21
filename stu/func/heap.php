<?php
$h=new SplMaxHeap();


for($i=0;$i<10;$i++){
    $h->insert($i);
}
$h->insert(100);
print_r($h);
while(!$h->isEmpty()){
    echo $h->top().PHP_EOL;
    $h->extract();
}