INSERT INTO `sea_arcrank` VALUES (1, 0, '开放浏览', 5, 0, 0, ''),
(2, 10, '注册会员', 5, 0, 100, ''),
(3, 50, '中级会员', 5, 300, 200, ''),
(4, 100, '高级会员', 5, 800, 500, '');

INSERT INTO `sea_mytag` VALUES('1','areasearch','地区搜索','1251590919','<a href=\'/{seacms:sitepath}search.php?searchtype=2&searchword=大陆\' target=\"_blank\">大陆</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=2&searchword=香港\'target=\"_blank\">香港</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=2&searchword=台湾\'target=\"_blank\">台湾</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=2&searchword=日本\' target=\"_blank\">日本</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=2&searchword=韩国\' target=\"_blank\">韩国</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=2&searchword=欧美\' target=\"_blank\">欧美</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=2&searchword=其它\' target=\"_blank\">其它</a>');
INSERT INTO `sea_mytag` VALUES('2','yearsearch','按发行年份查看电影','1251509338','<a href=\'/{seacms:sitepath}search.php?searchtype=3&searchword=2009\' target=\"_blank\">2009</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=3&searchword=2008\'target=\"_blank\">2008</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=3&searchword=2007\' target=\"_blank\">2007</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=3&searchword=2006\' target=\"_blank\">2006</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=3&searchword=2005\' target=\"_blank\">2005</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=3&searchword=2004\' target=\"_blank\">2004</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=3&searchword=2003\' target=\"_blank\">2003</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=3&searchword=2002\' target=\"_blank\">2002</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=3&searchword=2001\' target=\"_blank\">2001</a>');
INSERT INTO `sea_mytag` VALUES('3','actorsearch','演员名字','1251590973','<a href=\'/{seacms:sitepath}search.php?searchtype=1&searchword=成龙\' target=\"_blank\">成龙</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=1&searchword=周星驰\'target=\"_blank\">周星驰</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=1&searchword=周润发\'target=\"_blank\">周润发</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=1&searchword=舒淇\' target=\"_blank\">舒淇</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=1&searchword=葛优\' target=\"_blank\">葛优</a> \r\n<a href=\'/{seacms:sitepath}search.php?searchtype=1&searchword=周杰伦\' target=\"_blank\">周杰伦</a> ');
INSERT INTO `sea_mytag` VALUES('4','nav_bottom_banner','导航栏下方通栏广告','1251591021','aaaaaaaaaaaaaaaaaaaaaa\r\n$$$\r\nbbbbbbbbbbbbbbbbbbbbbb\r\n$$$\r\neeeeeeeeeeeeeeeeeeeeee');

INSERT INTO `sea_co_cls` (`id`, `clsname`, `sysclsid`,`cotype`) VALUES (1, '大陆', 0,0);
INSERT INTO `sea_co_cls` (`id`, `clsname`, `sysclsid`,`cotype`) VALUES (2, '香港', 0,0);
INSERT INTO `sea_co_cls` (`id`, `clsname`, `sysclsid`,`cotype`) VALUES (3, '台湾', 0,0);
INSERT INTO `sea_co_cls` (`id`, `clsname`, `sysclsid`,`cotype`) VALUES (4, '日本', 0,0);
INSERT INTO `sea_co_cls` (`id`, `clsname`, `sysclsid`,`cotype`) VALUES (5, '韩国', 0,0);
INSERT INTO `sea_co_cls` (`id`, `clsname`, `sysclsid`,`cotype`) VALUES (6, '欧美', 0,0);
INSERT INTO `sea_co_cls` (`id`, `clsname`, `sysclsid`,`cotype`) VALUES (7, '日韩', 0,0);
INSERT INTO `sea_co_cls` (`id`, `clsname`, `sysclsid`,`cotype`) VALUES (8, '中国', 0,0);

INSERT INTO `sea_myad` VALUES (1,'channel200x200bt','channel200x200bt',1344397254,'测试广告1','document.writeln(\"<div class=\\\"right_b_neirong\\\"><\\/div>\")');
INSERT INTO `sea_myad` VALUES (2,'channel200x200top','channel200x200top',1344397271,'测试广告2','document.writeln(\"<div class=\\\"right_t_neirong\\\"><\\/div>\")');
INSERT INTO `sea_myad` VALUES (3,'channel728x90','channel728x90',1344397299,'测试广告3','document.writeln(\"<div class=\\\"guanggao_neirong\\\"><\\/div>\")');

INSERT INTO `sea_type` (`tid`, `upid`, `tname`, `tenname`, `torder`, `templist`,`templist_1`,`templist_2`,`keyword`,`description`, `ishidden`, `unionid`, `tptype`) VALUES
(1,0,'电影','dy',1,'channel.html','content.html','play.html','','',0,'',0),
(2,0,'连续剧','tv',2,'channel.html','content.html','play.html','','',0,'',0),
(3,0,'动漫','dm',3,'channel.html','content.html','play.html','','',0,'',0),
(4,0,'综艺','zy',4,'channel.html','content.html','play.html','','',0,'',0),
(5,1,'动作片','dongzuo',5,'channel.html','content.html','play.html','','',0,'',0),
(6,1,'喜剧片','xiju',6,'channel.html','content.html','play.html','','',0,'',0),
(7,1,'恐怖片','kongbu',7,'channel.html','content.html','play.html','','',0,'',0),
(8,1,'科幻片','kehuan',8,'channel.html','content.html','play.html','','',0,'',0),
(9,1,'爱情片','aiqing',9,'channel.html','content.html','play.html','','',0,'',0),
(10,1,'剧情片','juqing',10,'channel.html','content.html','play.html','','',0,'',0),
(11,1,'战争片','zhanzheng',11,'channel.html','content.html','play.html','','',0,'',0),
(12,1,'纪录片','jilu',12,'channel.html','content.html','play.html','','',0,'',0),
(13,1,'动画片','donghua',13,'channel.html','content.html','play.html','','',0,'',0),
(14,2,'国产剧','guochanju',14,'channel.html','content.html','play.html','','',0,'',0),
(15,2,'欧美剧','oumeiju',15,'channel.html','content.html','play.html','','',0,'',0),
(16,2,'香港剧','xianggangju',16,'channel.html','content.html','play.html','','',0,'',0),
(17,2,'韩国剧','hanguoju',17,'channel.html','content.html','play.html','','',0,'',0),
(18,2,'台湾剧','taiwanju',18,'channel.html','content.html','play.html','','',0,'',0),
(19,2,'日本剧','ribenju',19,'channel.html','content.html','play.html','','',0,'',0),
(20,2,'泰国剧','taiguoju',20,'channel.html','content.html','play.html','','',0,'',0),
(21,2,'海外剧','haiwaiju',21,'channel.html','content.html','play.html','','',0,'',0),
(22,3,'国产动漫','guochandongman',22,'channel.html','content.html','play.html','','',0,'',0),
(23,3,'日韩动漫','rihandongman',23,'channel.html','content.html','play.html','','',0,'',0),
(24,3,'欧美动漫','oumeidongman',24,'channel.html','content.html','play.html','','',0,'',0),
(25,3,'港台动漫','gangtaidongman',25,'channel.html','content.html','play.html','','',0,'',0),
(26,3,'海外动漫','haiwaidongman',26,'channel.html','content.html','play.html','','',0,'',0),
(27,4,'大陆综艺','daluzongyi',27,'channel.html','content.html','play.html','','',0,'',0),
(28,4,'港台综艺','gangtaizongyi',28,'channel.html','content.html','play.html','','',0,'',0),
(29,4,'日韩综艺','rihanzongyi',29,'channel.html','content.html','play.html','','',0,'',0),
(30,4,'欧美综艺','oumeizongyi',30,'channel.html','content.html','play.html','','',0,'',0),
(31,4,'海外综艺','haiwaizongyi',31,'channel.html','content.html','play.html','','',0,'',0);

INSERT INTO `sea_member_group` (`gid`, `gname`, `gtype`, `g_auth`, `g_upgrade`) VALUES ('1', '游客用户', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16', '1,2,3', '0');
INSERT INTO `sea_member_group` (`gid`, `gname`, `gtype`, `g_auth`, `g_upgrade`) VALUES ('2', '注册用户', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16', '1,2,3', '0');
INSERT INTO `sea_member_group` (`gid`, `gname`, `gtype`, `g_auth`, `g_upgrade`) VALUES ('3', '超级会员', '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16', '1,2,3', '100');

INSERT INTO `sea_jqtype` (`tid`,`upid`, `tname`, `ishidden`) VALUES
(1,0, '解密', 0),
(2,0, '乡村', 0),
(3,0,'都市', 0),
(4,0, '少儿', 0),
(5,0, '对话', 0),
(6,1, '搞笑', 0),
(7,1, '恐怖', 0),
(8,1, '宫廷', 0),
(9,1, '剧情', 0),
(10,2, '言情', 0),
(11,2, '家庭', 0),
(12,2, '励志', 0),
(13,2, '偶像', 0),
(14,2, '时装', 0),
(15,3, '年代', 0),
(16,3, '悬疑', 0),
(17,3, '古装', 0),
(18,4, '热血', 0),
(19,4, '同人', 0),
(20,4, '耽美', 0);