<?php
/**
 * 参数过滤
 */
return array(
    'FILTER' => array(
        '<','>','src=','insert into','delete from','select * from','show table','show create table','#','script',';',
    ),
    'LOGFILTER' => array(
        'insert into','delete from','select * from','show table','show create table'
    ),
);