<?php
return [
    'db' => [
        'host' => 'localhost',
        'dbname' => '114514',
        'user' => '114514',
        'pass' => '114514',
        'charset' => 'utf8mb4',
    ],
    'openai' => [
        'api_url' => 'https://api.114514.xxx',
        'api_key' => 'sk-114514',
    ],
    'site' => [
        'name' => 'AiCoding',
        'base_url' => 'http://aicoding.xxx.xxx',
        'initial_points' => 100,
    ],
    'mail' => [
        'host' => 'smtp.qq.com',  // 邮箱SMTP服务器
        'username' => '114514@qq.com',  // 邮箱
        'password' => '114513',  // 邮箱SMTP授权码
        'port' => 465,  // 邮箱SSL端口
        'from_name' => 'AICoding',
        'verification_subject' => 'AICoding - 邮箱验证码',
        'allowed_domains' => [
            'buaa.edu.cn'
        ] //白名单
    ],
    'baidu' => [
        'api_key' => 'xxx',
        'secret_key' => 'xxxxxxxx'
    ]//百度OCR
]; 