<?php
$h=new SplMaxHeap();


for($i=1;$i<=5;$i++){
    $h->insert('task'.$i);
}
//$h->insert(100);
print_r($h);
while(!$h->isEmpty()){
    echo $h->top().PHP_EOL;
    $h->extract();
}