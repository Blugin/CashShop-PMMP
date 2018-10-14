# CashShop-PMMP
A PocketMine-MP Plugin | CashShop

# 사용법

/캐쉬, /캐쉬샵, /캐쉬관리

# 개발자를 위한 API

use alvin0319\CashShop;

CashShop::getInstance()->addCash($name, $amount);// $name 에게 $amount 만큼의 캐쉬를 지급합니다

CashShop::getInstance()->removeCash($name, $amount);// $name 에게서 $amount 만큼의 캐쉬를 뺏어갑니다
