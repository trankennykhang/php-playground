<?php
$a = function($c) {
    return function($b){
        print $b;
    };
};
$a(123)('3123');