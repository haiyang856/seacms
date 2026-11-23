var ajax = new AJAX();ajax.setcharset("utf-8");
function reportErr(id){openWin("/"+sitePath+"err.php?id="+id,400,220,350,250,0)}

function AddFav(vid,uid)
{
	ajax.get( 
		"/"+sitePath+"include/ajax.php?action=addfav&id="+vid+"&uid="+uid,
		function(obj) {
			if (obj.responseText=="err"){
				alert("请先登录!");
				location.href="/"+sitePath+"member/login.php";
			}else{
				alert("收藏成功!");
			}
		}
	);
}

function viewComment(id,page){
	var url;
	if (page.length==0){url=id;}else{url="/"+sitePath+"comment.php?id="+id+"&page="+page;}
	ajax.get( 
		url,
		function(obj) {
			if (obj.responseText=="err"){
				set(document.getElementById("comment_list"),"<font color='red'>发生错误</font>")	
			}else{
				set(document.getElementById("comment_list"),obj.responseText)	
			}
		}
	);		
}

function submitComment(id){
	if(document.getElementById("username").value.length<1){alert('请填写昵称');return false;}
	if(document.getElementById("msg").value.length<1){alert('请填写内容');return false;}
	ajax.postf(
		document.getElementById("f_comment"),
		function(obj){if(obj.responseText=="ok"){viewComment(id,1);alert('感谢您的评论!');}else if(obj.responseText=="validateerr"){alert('验证码错误，请点击验证码图片更新验证码！');}else if(obj.responseText=="havecomment"){alert('发布过于频繁，请稍后再操作！');}else if(obj.responseText=="ipcomment"){alert('您所在的ip不能评论');}else if(obj.responseText=="wordcomment"){alert('您的评论中有禁用词语，不能评论');}/*else{alert(obj.responseText);}*/}
	);
}

function diggVideo(id,div){
	ajax.get(
		"/"+sitePath+"include/ajax.php?id="+id+"&action=digg",
		function (obj){
			var returnValue=Number(obj.responseText)
			if (!isNaN(returnValue)){set(document.getElementById(div),returnValue);alert('成功顶贴！');}else if(obj.responseText=="err"){alert('顶贴失败')}else if(obj.responseText=="havescore"){alert('您已经执行过顶踩操作！')}	
		}
	);	
}

function treadVideo(id,div){
	ajax.get(
		"/"+sitePath+"include/ajax.php?id="+id+"&action=tread",
		function (obj){
			var returnValue=Number(obj.responseText)
			if(!isNaN(returnValue)){set(document.getElementById(div),returnValue);alert('踩贴成功！');}else if(obj.responseText=="err"){alert('踩贴失败')}	else if(obj.responseText=="havescore"){alert('您已经执行过顶踩操作！')}	
		}
	);	
}

function diggNews(id,div){
	ajax.get("/"+sitePath+"include/ajax.php?id="+id+"&action=diggnews",function (obj){
			var returnValue=Number(obj.responseText)
			if (!isNaN(returnValue)){set(document.getElementById(div),returnValue);alert('成功顶贴！');}else if(obj.responseText=="err"){alert('顶贴失败')}else if(obj.responseText=="havescore"){alert('您已经执行过顶踩操作！')}	
		}
	);
}

function treadNews(id,div){
	ajax.get("/"+sitePath+"include/ajax.php?id="+id+"&action=treadnews",function (obj){
			var returnValue=Number(obj.responseText)
			if(!isNaN(returnValue)){set(document.getElementById(div),returnValue);alert('踩贴成功！');}else if(obj.responseText=="err"){alert('踩贴失败')}	else if(obj.responseText=="havescore"){alert('您已经执行过顶踩操作！')}	
		}
	);	
}

function alertFrontWin(zindex,width,height,alpha,str){
	openWindow(zindex,width,height,alpha)
	set(document.getElementById("msgbody"),str)
}

function getAspParas(suffix){
	var cur_url=location.href;
	var urlParas=location.search;
	if (cur_url.indexOf("?")>0){
		
		if(cur_url.indexOf("-")>0){
			return urlParas.substring(1,urlParas.indexOf(suffix)).split('-');
		}
		else
		{
			var tmpurl = cur_url.split("?");
			var mytemp = tmpurl[1]; 
			var superx = mytemp.split("&");
			var myarr = new Array(superx[0],superx[1],superx[2]);		
			return myarr;	
		}
	}else{
		return cur_url.substring(cur_url.lastIndexOf("/")+1,cur_url.indexOf(suffix)).split('-')	//伪静态
	}
}

function getHtmlParas(suffix){
		var cur_url=location.href;
		return cur_url.substring(cur_url.lastIndexOf("/")+1,cur_url.indexOf(suffix)).split('-')	//静态

		//var urlParas=location.href;
		//var tempurl = urlParas.replace("http://",""); //去掉 http
		//tempurl = tempurl.replace("//","/"); //避免出现双杠现象
		//var temparr = tempurl.split('/'); //通过 / 划分数组
		//var hosturl = "http://" + temparr[0]; // 主域名
		//var filename = temparr[temparr.length-1]; //文件名
		//var middle = "";
		//var filearr = filename.split('-');
		//middle = urlParas.replace(filename,"")+filearr[0];
		//var myarr = new Array(middle,filearr[1],filename.split('.')[0].split('-')[2]);
		//return myarr;
}

function handleParas(para1,para2){
	var i,fromArray,len1,len2,urlArray,j,dataStr,dataArray
	if (isNaN(para1) || isNaN(para2)){return false}
	fromArray=VideoInfoList.split('$$$')
	len1=fromArray.length;if(para2>len1-1){para2=len1-1}
	for (i=0;i<len1;i++){if (para2==i){urlArray=fromArray[i].split('$$')[1].split('#');len2=urlArray.length;if(para1>len2-1){para1=len2-1};for (j=0;j<len2;j++){if (para1==j){dataStr=urlArray[j];dataArray=dataStr.split('$');return dataArray}}}}
}


function regexpSplice(url,pattern,spanstr) {
   pattern.exec(url);
   return (RegExp.$1+spanstr+ RegExp.$2);
}

function getPageValue(pageGoName){
	var pageGoArray,i,len,pageValue
	pageGoArray=getElementsByName('input',pageGoName) ; len=pageGoArray.length
	for(i=0;i<len;i++){
		pageValue=pageGoArray[i].value;
		if(pageValue.length>0){return pageValue;}
	}
	return ""
}

function getPageGoUrl(maxPage,pageDiv,type,listpagename){
	var str,goUrl
	var url=location.href
	pageNum=getPageValue(pageDiv)
	if (pageNum.length==0||isNaN(pageNum)){alert('输入页码非法');return false;}
	if(pageNum>maxPage){pageNum=maxPage;}
	if(pageNum<1){pageNum=1;}
	switch (type){
		case 1 :
			//dynamic
			//http://127.0.0.1/xxxx/?1.html ; http://127.0.0.1/xxxx/?1-2.html
			str=(pageNum==1)?'':"-"+pageNum;
			goUrl=regexpSplice(url,/(\/\/\S+\?\d+)[-]{0,1}\d*(\.html|\.htm|\.shtml|\.shtm|\.asp)/,str);
			break;
		case 2 :
			//dir1
			//http://127.0.0.1/xxxx/xxxx.html ; http://127.0.0.1/xxxx/xxxx2.html
			if(url.lastIndexOf("/")==(url.length-1)){url+=listpagename}
			str=(pageNum==1)?'':pageNum;;
			goUrl=regexpSplice(url,/(\/\/\S+?)[\d]*(\.html|\.htm|\.shtml|\.shtm|\.asp|\.php)/,str);
			break;
		case 3 :
			//dir2
			//http://127.0.0.1/xxxx/1.html ; http://127.0.0.1/xxxx/1_2.html
			str=(pageNum==1)?'':"_"+pageNum;
			goUrl=(url.split('_').length<3)?regexpSplice(url,/(\/\/\S+\d+?)(\.html|\.htm|\.shtml|\.shtm|\.asp|\.php)/,str):regexpSplice(url,/(\/\/\S+\d+?)_\d+(\.html|\.htm|\.shtml|\.shtm|\.asp)/,str);
			if(goUrl.indexOf('//')==-1){goUrl=regexpSplice(url,/(\/\/\S+_\d+?)(\.html|\.htm|\.shtml|\.shtm|\.asp|\.php)/,str);}
			break;
		case 5 :
			//zt
			//http://127.0.0.1/topiclist/?5.html ; http://127.0.0.1/topiclist/?5-2.html
			//http://127.0.0.1/topiclist/xxx.html ; http://127.0.0.1/topiclist/xxx-2.html
			str=(pageNum==1)?'':"-"+pageNum;
			goUrl=(url.split('-').length<2)?regexpSplice(url,/(\/\/\S+\d+?)(\.html|\.htm|\.shtml|\.shtm|\.asp|\.php)/,str):regexpSplice(url,/(\/\/\S+\d+?)-\d+(\.html|\.htm|\.shtml|\.shtm|\.asp)/,str);
			if(goUrl.indexOf('//')==-1){goUrl=regexpSplice(url,/(\/\/\S+\d+?)(\.html|\.htm|\.shtml|\.shtm|\.asp|\.php)/,str);}
			break;
		case 6 :
			//forged
			str=(pageNum==1)?'':"-"+pageNum;
			goUrl=regexpSplice(url,/(\/\/\S+?)[-]{0,1}[\d]{0,1}(\.html|\.htm|\.shtml|\.shtm|\.asp|\.php)/,str);
			break;
		case 7:
			//ztindex
			//http://127.0.0.1/topic/?1.html
			str=pageNum;
			goUrl=regexpSplice(url,/(\/\/\S+\?+?)\d+(\.html|\.htm|\.shtml|\.shtm|\.asp|\.php)/,str);
			break;
			}
	location.href=goUrl;
}

function goSearchPage(maxPage,pageDiv,searchtype,searchword){
	var pageNum=getPageValue(pageDiv)
	if (pageNum.length==0||isNaN(pageNum)){alert('输入页码非法');return false;}
	if(pageNum>maxPage){pageNum=maxPage;}
	if(pageNum<1){pageNum=1;}
	location.href='?page='+pageNum+'&searchword='+searchword+'&searchtype='+searchtype;
}

function goCascadePage(maxPage,pageDiv,searchwhere){
	var pageNum=getPageValue(pageDiv)
	if (pageNum.length==0||isNaN(pageNum)){alert('输入页码非法');return false;}
	if(pageNum>maxPage){pageNum=maxPage;}
	if(pageNum<1){pageNum=1;}
	location.href='?page='+pageNum+'&'+searchwhere;
}

function leaveWord(){
	if(document.getElementById("m_author").value.length<1){alert('昵称必须填写');return false;}
	if(document.getElementById("m_content").value.length<1){alert('内容必须填写');return false;}
	ajax.postf(
		document.getElementById("f_leaveword"),
		function(obj){if(obj.responseText=="ok"){viewLeaveWordList(1);alert('留言成功！');document.getElementById("m_content").value='';}else if(obj.responseText=="haveleave"){alert('发布过于频繁，请稍后再操作！');}else{alert('发生错误');}}
	);
}

function leaveWordgbook(){
	if(document.getElementById("vdcode").value.length<1){alert('验证码必须填写');return false;}
	if(document.getElementById("m_content").value.length<1){alert('内容必须填写');return false;}
	var obj=document.getElementById("m_content").value;
	if(!(/.*[\u4e00-\u9fa5]+.*$/.test(obj)))
	{alert("留言内容请包含中文！");return false;}
	return true;
}

function leaveWordgbook2(){
	if(document.getElementById("m_content").value.length<1){alert('内容必须填写');return false;}
	var obj=document.getElementById("m_content").value;
	if(!(/.*[\u4e00-\u9fa5]+.*$/.test(obj)))
	{alert("留言内容请包含中文！");return false;}
	return true;
}

function getVideoHit(vid){
	ajax.get(
		"/"+sitePath+"include/ajax.php?action=hit&id="+vid,
		function (obj){
			var result=obj.responseText
			if(result=="err"){set(document.getElementById('hit'),'发生错误')}else{set(document.getElementById('hit'),result);}
		}
	);				
}

function member()
{
	ajax.get( 
		"/"+sitePath+"include/ajax.php?action=member",
		function (obj){
			var result=obj.responseText;
			set(document.getElementById('seacms_member'),result);
		}
	);
}

function getNewsHit(nid){
	ajax.get(
		"/"+sitePath+"include/ajax.php?action=hitnews&id="+nid,
		function (obj){
			var result=obj.responseText
			
			if(result=="err"){set(document.getElementById('hit'),'发生错误')}else{set(document.getElementById('hit'),result);}
		}
	);		
}

function markscore0(vd,d,t,s,l,ac){
	var alt=['很差','较差','还行','推荐','力荐'],url=ac=='news' ? ["/"+sitePath+"include/ajax.php?id="+vd+"&action=newsscore","/"+sitePath+"include/ajax.php?id="+vd+"&action=scorenews&score="] : ["/"+sitePath+"include/ajax.php?id="+vd+"&action=videoscore","/"+sitePath+"include/ajax.php?id="+vd+"&action=score&score="],
	x=d,y=(Math.round(s / x * 100) / 100) || 0,id='BT'+(new Date()).getTime();
	document.write('<div style="padding:5px 10px;border:1px solid #CCC">\
			<div style="color:#000"><strong>我来评分(请您参与评分，体现您的观点)</strong></div>\
			<div>共 <strong style="font-size:14px;color:red" id="MARK_B1"> '+x+' </strong> 个人评分， 平均分 <strong style="font-size:14px;color:red" id="MARK_B2"> '+y+' </strong>， 总得分 <strong style="font-size:14px;color:red" id="MARK_B3"> '+s+' </strong> <strong style="font-size:14px;color:red" id="MARK_B4"></strong></div>\
			<div>');
	for(var i=0;i<=l;i++) document.write('<input type="radio" name="score" id="sint'+i+'" value="1" title="'+alt[parseInt(i/l*(alt.length-1))]+'"/><label for="sint'+i+'">'+i+'</label>');
	document.write('&nbsp;<input type="button" value=" 评分 " id="'+id+'" style="width:55px;height:21px"/>\
			</div>\
		</div>');
	document.getElementById(id).onclick=function (){
		for(var i=0;i<=l;i++) if(document.getElementById('sint'+i).checked)break;
		if(i>l){alert('你还没选取分数');return;}
		ajax.get(url[1]+i,function (obj){
			if((''+obj.responseText).indexOf("havescore")!=-1){
				alert('你已经评过分啦');
			}else{
				document.getElementById('MARK_B4').innerHTML="评分成功！";

				alert('感谢你的参与!');
			}
		});
		this.disabled=true;
	}
	if(new Date().toGMTString()!=new Date(document.lastModified).toGMTString()) return ajax.get(url[0],function (obj){
		var a=obj.responseText
		try{
			a.replace(/\[(\d+),(\d+),(\d+)\]/i,function ($0,d,t,s){
				var x=parseInt(d),y=(Math.round(parseInt(s) / x * 100) / 100) || 0;
				document.getElementById('MARK_B1').innerHTML=x;
				document.getElementById('MARK_B2').innerHTML=y;
				document.getElementById('MARK_B3').innerHTML=s;
			});
		}catch(ig){}
	});
}
function showpf()
	{document.getElementById('seacmsvpf1').style.display="none";document.getElementById('seacmsvpf2').style.display="inline";}
function markscore1(vd,d,t,s,l,ac){
	var alt=['很差','较差','还行','推荐','力荐'],src=['/'+sitePath+'pic/star0.gif','/'+sitePath+'pic/star1.gif'],url=ac=='news' ? ["/"+sitePath+"include/ajax.php?id="+vd+"&action=newsscore","/"+sitePath+"include/ajax.php?id="+vd+"&action=scorenews&score="] : ["/"+sitePath+"include/ajax.php?id="+vd+"&action=videoscore","/"+sitePath+"include/ajax.php?id="+vd+"&action=score&score="],
	x=d,y=(Math.round(s / x * 100) / 100) || 0,id='STAR'+(new Date()).getTime();
	document.write('<span id="'+id+'" style="padding:5px">');
	
	document.write('<span id="seacmsvpf1" onmouseover=showpf()></span>');
	document.write('<span id="seacmsvpf2">');
	for(var i=1;i<=l;i++){
		document.write('<img id="'+i+'" src="'+src[i<=y ? 0 : 1]+'" title="'+alt[parseInt(i/l*(alt.length-1))]+'" style="cursor:pointer">');
	}
	document.write('</span>');
	document.write('&nbsp;<strong style="font-size:14px;color:red" id="MARK_B2"></strong>(<span id="MARK_B3"></span>)</span>');
	var dc=document.getElementById(id),im=dc.getElementsByTagName('img');
	for(var i=0;i<im.length;i++){
		im[i].onclick=function (){
			var x=parseInt(this.id);
			ajax.get(url[1]+x,function (obj){
				if((''+obj.responseText).indexOf("havescore")!=-1){
					alert('你已经评过分啦');
				}else{
					alert('感谢你的参与!');
					y=x;dc.onmouseout();
				}
			});
		}
		im[i].onmouseover=function (){
			var x=parseInt(this.id);
			for(var i=0;i<im.length;i++) im[i].src=src[x>=parseInt(im[i].id) ? 0 : 1];
		}
	}
	dc.onmouseout=function (){
		for(var i=0;i<im.length;i++) im[i].src=src[y>=parseInt(im[i].id) ? 0 : 1];
		document.getElementById('MARK_B2').innerHTML=y;document.getElementById('MARK_B3').innerHTML=y>0 ? alt[parseInt(y/l*(alt.length-1))] : '请选择' ;
	}
	if(new Date().toGMTString()!=new Date(document.lastModified).toGMTString()) return ajax.get(url[0],function (obj){
		var a=obj.responseText
		try{
			a.replace(/\[(\d+),(\d+),(\d+)\]/i,function ($0,d,t,s){
				var x=parseInt(d);y=(Math.round(parseInt(s) / x * 100) / 100) || 0;
				dc.onmouseout();
			});
		}catch(ig){}
	});
	dc.onmouseout();
}


function markNews2(vid,style,len){

	ajax.get(
		"/"+sitePath+"include/ajax.php?action=npingfen&id="+vid,
		function (obj){
			var result=obj.responseText;
			result=result.split(",");
			num=result[0];
			sum=result[1];
			sc=result[2];
			if(style==1){
			//星星评分
			document.getElementById('seacmsvpf2').style.display="none";
			id='STAR'+(new Date()).getTime();
			for(var ii=1;ii<=len;ii++){
			if(ii>sc){p=1;}else{p=0;}
			document.getElementById('seacmsvpf1').innerHTML+='<img iid='+ii+' src="/pic/star'+p+'.gif" style="cursor:pointer">';
			}
			document.getElementById('MARK_B2').innerHTML=sc;
			document.getElementById('MARK_B3').innerHTML=''+num+'次评分';
			}else{
			//单选评分
			document.getElementById('MARK_B2').innerHTML=sc;
			document.getElementById('MARK_B3').innerHTML=sum;
			document.getElementById('MARK_B1').innerHTML=num;
			}
		}
	);			
}



function markNews(vd,d,t,s,l,c){
	window['markscore'+(c==1 ? 1 : 0)](vd,d,t,s,parseInt(l)<0 ? 5 : l,'news');
}


function markVideo2(vid,style,len){

	ajax.get(
		"/"+sitePath+"include/ajax.php?action=vpingfen&id="+vid,
		function (obj){
			var result=obj.responseText;
			result=result.split(",");
			num=result[0];
			sum=result[1];
			sc=result[2];
			if(style==1){
			//星星评分
			document.getElementById('seacmsvpf2').style.display="none";
			id='STAR'+(new Date()).getTime();
			for(var ii=1;ii<=len;ii++){
			if(ii>sc){p=1;}else{p=0;}
			document.getElementById('seacmsvpf1').innerHTML+='<img iid='+ii+' src="/pic/star'+p+'.gif" style="cursor:pointer">';
			}
			document.getElementById('MARK_B2').innerHTML=sc;
			document.getElementById('MARK_B3').innerHTML=''+num+'次评分';
			}else{
			//单选评分
			document.getElementById('MARK_B2').innerHTML=sc;
			document.getElementById('MARK_B3').innerHTML=sum;
			document.getElementById('MARK_B1').innerHTML=num;
			}
		}
	);			
}




function markVideo(vd,d,t,s,l,c){
	window['markscore'+(c==1 ? 1 : 0)](vd,d,t,s,parseInt(l)<0 ? 5 : l);
}

function addFavorite(sURL, sTitle){
	try{ window.external.addFavorite(sURL, sTitle);}
		catch (e){
			try{window.sidebar.addPanel(sTitle, sURL, "");}
			catch (e)
				{alert("加入收藏失败，请使用Ctrl+D进行添加");}
		}
}

function setHome(obj,vrl,url){
    try{obj.style.behavior='url(#default#homepage)';obj.setHomePage(vrl);
	this.style.behavior='url(#default#homepage)';this.setHomePage(url);}
        catch(e){
            if(window.netscape){
                try{netscape.security.PrivilegeManager.enablePrivilege("UniversalXPConnect");}  
                   catch (e){alert("此操作被浏览器拒绝！请手动设置");}
                   var prefs = Components.classes['@mozilla.org/preferences-service;1'].getService(Components.interfaces.nsIPrefBranch);
                   prefs.setCharPref('browser.startup.homepage',vrl);
             }
      }
}

function addFace(id) {
	document.getElementById('m_content').value += '[ps:' + id +']';
}

function openWin(url,w,h,left,top,resize){
	window.open(url,'New_Win','toolbars=0, scrollbars=0, location=0, statusbars=0,menubars=0, resizable='+(resize)+',width='+w+',height='+h+',left='+left+',top='+top);
}

function loadSlide(w,h){
	var type=1   //type=0不显示幻灯片右侧列表；type=1显示幻灯片右侧列表
	document.write('<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="//download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,28,0" width="'+w+'" height="'+h+'"><param name="movie" value="/'+sitePath+'pic/slide/slide.swf" /><param name="quality" value="high"><param   name="wmode"   value="transparent"><param name="allowscriptaccess" value="always"><param name="allowfullscreen" value="true"><param name="flashvars" value="type='+type+'&domain=/'+sitePath+'pic/slide/"><embed src="/'+sitePath+'pic/slide/slide.swf" flashvars="type='+type+'&domain=/'+sitePath+'pic/slide/" quality="high" pluginspage="//www.adobe.com/shockwave/download/download.cgi?P1_Prod_Version=ShockwaveFlash" type="application/x-shockwave-flash" allowfullscreen="true" width="'+w+'" height="'+h+'"></embed></object>');
}

function stringReplaceAll(str,findstr,replacestr){var raRegExp = new RegExp(findstr,"g");return str.replace(raRegExp,replacestr);}

function addRemoteFavor(){
	ajax.get(
		"/"+sitePath+"include/ajax.php?action=favorAjax&id="+play_vid+"&faction=add",
		function (obj){alert(obj.responseText)}
	);
}

var base64DecodeChars = new Array(
    -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
    -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
    -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, 62, -1, -1, -1, 63,
    52, 53, 54, 55, 56, 57, 58, 59, 60, 61, -1, -1, -1, -1, -1, -1,
    -1, 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14,
    15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, -1, -1, -1, -1, -1,
    -1, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40,
    41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, -1, -1, -1, -1, -1); 

function base64decode(str) {
    var c1, c2, c3, c4;
    var i, len, out;

    len = str.length;
    i = 0;
    out = "";
    while(i < len) {
    /* c1 */
    do {
        c1 = base64DecodeChars[str.charCodeAt(i++) & 0xff];
    } while(i < len && c1 == -1);
    if(c1 == -1)
        break;

    /* c2 */
    do {
        c2 = base64DecodeChars[str.charCodeAt(i++) & 0xff];
    } while(i < len && c2 == -1);
    if(c2 == -1)
        break;

    out += String.fromCharCode((c1 << 2) | ((c2 & 0x30) >> 4));

    /* c3 */
    do {
        c3 = str.charCodeAt(i++) & 0xff;
        if(c3 == 61)
        return out;
        c3 = base64DecodeChars[c3];
    } while(i < len && c3 == -1);
    if(c3 == -1)
        break;

    out += String.fromCharCode(((c2 & 0XF) << 4) | ((c3 & 0x3C) >> 2));

    /* c4 */
    do {
        c4 = str.charCodeAt(i++) & 0xff;
        if(c4 == 61)
        return out;
        c4 = base64DecodeChars[c4];
    } while(i < len && c4 == -1);
    if(c4 == -1)
        break;
    out += String.fromCharCode(((c3 & 0x03) << 6) | c4);
    }
    return out;
}

/* ==================== AI 影片推荐（前台右下角浮标 + 对话窗口） ==================== */
(function(){
	if(window.seaAiRecommendLoaded) return;
	window.seaAiRecommendLoaded = true;

	function onReady(fn){
		if(document.readyState === 'complete' || document.readyState === 'interactive'){
			setTimeout(fn, 1);
		}else if(document.addEventListener){
			document.addEventListener('DOMContentLoaded', fn, false);
		}else{
			window.attachEvent('onload', fn);
		}
	}

	function esc(str){
		return String(str == null ? '' : str).replace(/[&<>"']/g, function(ch){
			return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch];
		});
	}

	function formatText(str){
		return esc(str).replace(/\r\n|\r|\n/g, '<br>');
	}

	var SPARK_SVG = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.5l2 5.4 5.4 2-5.4 2-2 5.4-2-5.4-5.4-2 5.4-2 2-5.4z" fill="#fff"/><path d="M18.8 14.6l.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2z" fill="#fff" opacity=".85"/></svg>';
	var SEND_SVG = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M3.4 20.4l17.4-8.4L3.4 3.6 3.3 10l12.6 2-12.6 2 .1 6.4z" fill="#fff"/></svg>';
	var REFRESH_SVG = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 5V2L7 6l5 4V7a5 5 0 1 1-5 5H5a7 7 0 1 0 7-7z" fill="#fff"/></svg>';
	var FIRE_SVG = '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67zM11.71 19c-1.78 0-3.22-1.4-3.22-3.14 0-1.62 1.05-2.76 2.81-3.12 1.77-.36 3.6-1.21 4.62-2.58.39 1.29.59 2.65.59 4.04 0 2.65-2.15 4.8-4.8 4.8z" fill="currentColor"/></svg>';

	onReady(function(){
		// 仅在有 sitePath 的前台模板页面启用
		if(typeof window.sitePath === 'undefined') return;
		if(document.getElementById('aiRecLauncher')) return;

		var apiBase = '/' + (window.sitePath || '') + 'ai_recommend.php';
		var xhr = new XMLHttpRequest();
		xhr.open('GET', apiBase + '?action=status&_t=' + new Date().getTime(), true);
		xhr.onreadystatechange = function(){
			if(xhr.readyState !== 4) return;
			if(xhr.status !== 200) return;
			var data = null;
			try{ data = JSON.parse(xhr.responseText); }catch(e){ return; }
			if(data && data.ok && data.enabled) buildWidget(apiBase);
		};
		xhr.send(null);
	});

	function buildWidget(apiBase){
		if(document.getElementById('aiRecLauncher')) return;

		/* ---------- 样式 ---------- */
		var css = ''
+ '#aiRecLauncher{position:fixed;right:22px;bottom:92px;z-index:2147483000;width:58px;height:58px;border-radius:50%;cursor:pointer;background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 55%,#ec4899 100%);display:flex;align-items:center;justify-content:center;box-shadow:0 12px 28px rgba(99,102,241,.45);transition:transform .25s ease,box-shadow .25s ease;animation:aiRecPulse 2.8s ease-out infinite;-webkit-tap-highlight-color:transparent;}'
+ '#aiRecLauncher:hover{transform:translateY(-3px) scale(1.05);box-shadow:0 16px 34px rgba(99,102,241,.55);}'
+ '#aiRecLauncher svg{width:28px;height:28px;display:block;}'
+ '#aiRecLauncher .ai-rec-launcher-label{position:absolute;right:70px;top:50%;transform:translateY(-50%) translateX(6px);white-space:nowrap;background:rgba(15,23,42,.86);color:#fff;font:500 12px/1 -apple-system,"Microsoft YaHei",sans-serif;padding:7px 11px;border-radius:8px;opacity:0;pointer-events:none;transition:opacity .2s ease,transform .2s ease;}'
+ '#aiRecLauncher:hover .ai-rec-launcher-label{opacity:1;transform:translateY(-50%) translateX(0);}'
+ '@keyframes aiRecPulse{0%{box-shadow:0 12px 28px rgba(99,102,241,.45),0 0 0 0 rgba(139,92,246,.38);}70%{box-shadow:0 12px 28px rgba(99,102,241,.45),0 0 0 16px rgba(139,92,246,0);}100%{box-shadow:0 12px 28px rgba(99,102,241,.45),0 0 0 0 rgba(139,92,246,0);}}'
+ '#aiRecPanel{position:fixed;right:22px;bottom:162px;z-index:2147483001;width:392px;max-width:calc(100vw - 24px);height:600px;max-height:calc(100vh - 190px);background:#fff;border-radius:18px;box-shadow:0 26px 70px rgba(15,23,42,.30);display:none;flex-direction:column;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","Microsoft YaHei",sans-serif;}'
+ '#aiRecPanel.ai-rec-show{display:flex;animation:aiRecPanelIn .28s cubic-bezier(.2,.85,.3,1.1);}'
+ '@keyframes aiRecPanelIn{from{opacity:0;transform:translateY(14px) scale(.97);}to{opacity:1;transform:translateY(0) scale(1);}}'
+ '.ai-rec-header{display:flex;align-items:center;gap:10px;padding:14px 16px;background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 55%,#db2777 120%);color:#fff;position:relative;flex:none;}'
+ '.ai-rec-header:after{content:"";position:absolute;right:-30px;top:-40px;width:120px;height:120px;background:radial-gradient(circle,rgba(255,255,255,.28),rgba(255,255,255,0) 70%);border-radius:50%;}'
+ '.ai-rec-head-avatar{width:38px;height:38px;border-radius:12px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;flex:none;box-shadow:inset 0 0 0 1px rgba(255,255,255,.25);position:relative;z-index:1;}'
+ '.ai-rec-head-avatar svg{width:22px;height:22px;}'
+ '.ai-rec-head-text{flex:1;min-width:0;position:relative;z-index:1;}'
+ '.ai-rec-title{font-size:15px;font-weight:700;letter-spacing:.3px;}'
+ '.ai-rec-subtitle{font-size:11px;opacity:.82;margin-top:2px;}'
+ '.ai-rec-close{width:30px;height:30px;border:0;border-radius:9px;background:rgba(255,255,255,.16);color:#fff;font-size:20px;line-height:1;cursor:pointer;flex:none;transition:background .2s;position:relative;z-index:1;}'
+ '.ai-rec-close:hover{background:rgba(255,255,255,.3);}'
+ '.ai-rec-refresh{border:0;border-radius:9px;background:rgba(255,255,255,.16);color:#fff;font-size:12px;font-weight:600;padding:7px 9px;cursor:pointer;flex:none;transition:background .2s;position:relative;z-index:1;line-height:1;display:flex;align-items:center;gap:4px;}'
+ '.ai-rec-refresh:hover{background:rgba(255,255,255,.3);}'
+ '.ai-rec-refresh svg{width:13px;height:13px;display:block;}'
+ '.ai-rec-body{flex:1;overflow-y:auto;padding:16px;background:linear-gradient(180deg,#f7f8fc 0%,#f3f4fa 100%);scrollbar-width:thin;scrollbar-color:#c7d2fe transparent;}'
+ '.ai-rec-body::-webkit-scrollbar{width:6px;}'
+ '.ai-rec-body::-webkit-scrollbar-thumb{background:#c7d2fe;border-radius:3px;}'
+ '.ai-rec-msg{display:flex;align-items:flex-start;gap:8px;margin-bottom:14px;animation:aiRecMsgIn .25s ease;}'
+ '@keyframes aiRecMsgIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:translateY(0);}}'
+ '.ai-rec-msg.user{justify-content:flex-end;}'
+ '.ai-rec-avatar{width:30px;height:30px;border-radius:10px;background:linear-gradient(135deg,#8b5cf6,#ec4899);display:flex;align-items:center;justify-content:center;flex:none;box-shadow:0 4px 10px rgba(139,92,246,.3);}'
+ '.ai-rec-avatar svg{width:18px;height:18px;}'
+ '.ai-rec-content{max-width:84%;min-width:0;}'
+ '.ai-rec-bubble{display:inline-block;padding:10px 13px;border-radius:14px;border-bottom-left-radius:5px;background:#fff;color:#334155;font-size:13px;line-height:1.7;box-shadow:0 3px 12px rgba(15,23,42,.07);word-break:break-word;}'
+ '.ai-rec-msg.user .ai-rec-bubble{background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border-radius:14px;border-bottom-right-radius:5px;box-shadow:0 5px 16px rgba(99,102,241,.32);}'
+ '.ai-rec-movies{margin-top:10px;display:flex;flex-direction:column;gap:8px;}'
+ '.ai-rec-movie{display:flex;align-items:center;gap:10px;padding:8px 10px 8px 8px;background:#fff;border:1px solid #eceef6;border-radius:12px;text-decoration:none;color:inherit;box-shadow:0 3px 10px rgba(15,23,42,.05);transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease;}'
+ '.ai-rec-movie:hover{transform:translateY(-2px);border-color:#c7d2fe;box-shadow:0 10px 20px rgba(99,102,241,.16);}'
+ '.ai-rec-movie img{width:46px;height:64px;object-fit:cover;border-radius:8px;background:#e2e8f0;flex:none;display:block;}'
+ '.ai-rec-movie-main{flex:1;min-width:0;}'
+ '.ai-rec-movie-name{font-size:13px;font-weight:700;color:#1e293b;line-height:1.35;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}'
+ '.ai-rec-movie-meta{font-size:11px;color:#94a3b8;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}'
+ '.ai-rec-movie-reason{font-size:11.5px;color:#64748b;line-height:1.5;margin-top:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}'
+ '.ai-rec-badge{display:inline-block;font-size:10px;font-weight:700;color:#b45309;background:#fef3c7;border-radius:4px;padding:0 5px;margin-right:4px;vertical-align:1px;}'
+ '.ai-rec-movie-go{flex:none;color:#c7d2fe;font-size:20px;font-weight:700;line-height:1;}'
+ '.ai-rec-typing{display:flex;gap:4px;padding:14px 16px;background:#fff;border-radius:14px;border-bottom-left-radius:5px;box-shadow:0 3px 12px rgba(15,23,42,.07);}'
+ '.ai-rec-typing i{width:6px;height:6px;border-radius:50%;background:#a5b4fc;animation:aiRecBlink 1.2s infinite ease-in-out;}'
+ '.ai-rec-typing i:nth-child(2){animation-delay:.2s;}'
+ '.ai-rec-typing i:nth-child(3){animation-delay:.4s;}'
+ '@keyframes aiRecBlink{0%,80%,100%{opacity:.35;transform:translateY(0);}40%{opacity:1;transform:translateY(-3px);}}'
+ '.ai-rec-chips{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px 38px;}'
+ '.ai-rec-chip{font-size:12px;padding:7px 12px;border-radius:20px;background:#fff;border:1px solid #e0e7ff;color:#4f46e5;cursor:pointer;transition:all .2s ease;box-shadow:0 2px 6px rgba(99,102,241,.08);}'
+ '.ai-rec-chip:hover{background:#4f46e5;border-color:#4f46e5;color:#fff;}'
+ '.ai-rec-hot{margin:0 0 14px 38px;padding:10px 12px 11px;background:linear-gradient(135deg,#fff7ed,#fef2f2);border:1px solid #fed7aa;border-radius:13px;}'
+ '.ai-rec-hot-title{display:flex;align-items:center;gap:4px;font-size:11px;font-weight:700;color:#c2410c;margin-bottom:8px;}'
+ '.ai-rec-hot-title svg{width:13px;height:13px;}'
+ '.ai-rec-hot-chips{display:flex;flex-wrap:wrap;gap:6px;}'
+ '.ai-rec-hot-chip{font-size:12px;padding:5px 10px;border-radius:14px;background:#fff;border:1px solid #fdba74;color:#9a3412;cursor:pointer;transition:all .2s ease;}'
+ '.ai-rec-hot-chip:hover{background:#ea580c;border-color:#ea580c;color:#fff;}'
+ '.ai-rec-input{display:flex;align-items:flex-end;gap:8px;padding:11px 12px 6px;background:#fff;border-top:1px solid #eef0f6;flex:none;}'
+ '.ai-rec-input textarea{flex:1;min-height:38px;max-height:90px;border:0;outline:none;resize:none;background:#f4f5fa;border-radius:12px;padding:10px 12px;font-size:13px;line-height:1.5;color:#334155;font-family:inherit;box-sizing:border-box;}'
+ '.ai-rec-input textarea::placeholder{color:#a3adc2;}'
+ '.ai-rec-send{width:40px;height:38px;border:0;border-radius:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex:none;box-shadow:0 6px 14px rgba(99,102,241,.35);transition:transform .2s ease,opacity .2s;}'
+ '.ai-rec-send:hover{transform:translateY(-1px);}'
+ '.ai-rec-send:disabled{opacity:.55;cursor:not-allowed;transform:none;}'
+ '.ai-rec-send svg{width:18px;height:18px;}'
+ '.ai-rec-foot{flex:none;text-align:center;font-size:10px;color:#a3adc2;padding:4px 0 9px;background:#fff;}'
+ '@media (max-width:640px){#aiRecLauncher{right:16px;bottom:150px;width:52px;height:52px;}#aiRecLauncher .ai-rec-launcher-label{display:none;}#aiRecPanel{left:10px;right:10px;top:10px;bottom:10px;width:auto;height:auto;max-height:none;border-radius:16px;}}';

		var style = document.createElement('style');
		style.type = 'text/css';
		style.appendChild(document.createTextNode(css));
		document.getElementsByTagName('head')[0].appendChild(style);

		/* ---------- DOM ---------- */
		var launcher = document.createElement('div');
		launcher.id = 'aiRecLauncher';
		launcher.setAttribute('role', 'button');
		launcher.setAttribute('tabindex', '0');
		launcher.setAttribute('aria-label', 'AI 影片推荐');
		launcher.innerHTML = SPARK_SVG + '<span class="ai-rec-launcher-label">AI 影片推荐</span>';

		var panel = document.createElement('div');
		panel.id = 'aiRecPanel';
		panel.setAttribute('role', 'dialog');
		panel.setAttribute('aria-label', 'AI 影片推荐');
		panel.innerHTML = ''
+ '<div class="ai-rec-header">'
+   '<div class="ai-rec-head-avatar">' + SPARK_SVG + '</div>'
+   '<div class="ai-rec-head-text"><div class="ai-rec-title">AI 影片推荐</div><div class="ai-rec-subtitle">懂你口味 · 秒出片单</div></div>'
+   '<button type="button" class="ai-rec-refresh" title="换一批推荐方向">' + REFRESH_SVG + '<span>换一批</span></button>'
+   '<button type="button" class="ai-rec-close" aria-label="关闭">&times;</button>'
+ '</div>'
+ '<div class="ai-rec-body" id="aiRecBody"></div>'
+ '<div class="ai-rec-input"><textarea id="aiRecInput" rows="1" placeholder="想看什么类型的影片？"></textarea><button type="button" class="ai-rec-send" id="aiRecSend" aria-label="发送">' + SEND_SVG + '</button></div>'
+ '<div class="ai-rec-foot">内容由 AI 生成，仅供参考</div>';

		document.body.appendChild(launcher);
		document.body.appendChild(panel);

		var body = document.getElementById('aiRecBody');
		var input = document.getElementById('aiRecInput');
		var sendBtn = document.getElementById('aiRecSend');
		var closeBtn = panel.getElementsByClassName('ai-rec-close')[0];
		var refreshBtn = panel.getElementsByClassName('ai-rec-refresh')[0];

		var history = [];
		var sending = false;
		var typingRow = null;

		function scrollBottom(){
			setTimeout(function(){ body.scrollTop = body.scrollHeight; }, 30);
		}

		function avatarEl(){
			var el = document.createElement('div');
			el.className = 'ai-rec-avatar';
			el.innerHTML = SPARK_SVG;
			return el;
		}

		function addMsg(role, text){
			var row = document.createElement('div');
			row.className = 'ai-rec-msg ' + (role === 'user' ? 'user' : 'bot');
			var bubble = document.createElement('div');
			bubble.className = 'ai-rec-bubble';
			bubble.innerHTML = formatText(text);
			if(role === 'user'){
				row.appendChild(bubble);
			}else{
				var content = document.createElement('div');
				content.className = 'ai-rec-content';
				content.appendChild(bubble);
				row.appendChild(avatarEl());
				row.appendChild(content);
			}
			body.appendChild(row);
			scrollBottom();
			return row;
		}

		function addMovies(contentEl, movies){
			if(!movies || !movies.length) return;
			var wrap = document.createElement('div');
			wrap.className = 'ai-rec-movies';
			for(var i = 0; i < movies.length; i++){
				var m = movies[i] || {};
				var card = document.createElement('a');
				card.className = 'ai-rec-movie';
				card.href = m.url || '#';
				card.target = '_blank';
				card.rel = 'noopener';

				var img = document.createElement('img');
				img.alt = m.name || '';
				img.src = m.pic || '';
				img.loading = 'lazy';
				img.onerror = function(){ this.style.visibility = 'hidden'; };
				card.appendChild(img);

				var main = document.createElement('div');
				main.className = 'ai-rec-movie-main';

				var name = document.createElement('div');
				name.className = 'ai-rec-movie-name';
				name.innerHTML = esc(m.name || '');
				main.appendChild(name);

				var metaParts = [];
				if(m.year) metaParts.push(m.year);
				if(m.area) metaParts.push(m.area);
				if(m.genre) metaParts.push(m.genre);
				var meta = document.createElement('div');
				meta.className = 'ai-rec-movie-meta';
				var badge = '';
				if(parseFloat(m.douban) > 0) badge = '<span class="ai-rec-badge">豆瓣 ' + esc(m.douban) + '</span>';
				else if(parseFloat(m.score) > 0) badge = '<span class="ai-rec-badge">站内 ' + esc(m.score) + '</span>';
				meta.innerHTML = badge + esc(metaParts.join(' · '));
				main.appendChild(meta);

				if(m.reason){
					var reason = document.createElement('div');
					reason.className = 'ai-rec-movie-reason';
					reason.innerHTML = esc(m.reason);
					main.appendChild(reason);
				}
				card.appendChild(main);

				var go = document.createElement('span');
				go.className = 'ai-rec-movie-go';
				go.innerHTML = '&rsaquo;';
				card.appendChild(go);

				wrap.appendChild(card);
			}
			contentEl.appendChild(wrap);
		}

		// 推荐方向词库：1000 条（100 条精选 + 900 条模板组合）
		var CHIP_POOL = (function(){
			var curated = [
				'推荐几部高分科幻片', '最近有什么好看的悬疑剧', '适合周末放松的喜剧', '来点烧脑的犯罪片',
				'推荐几部催泪爱情片', '想看热血的动作片', '有没有治愈系的电影', '推荐几部经典港片',
				'来几部日韩悬疑电影', '推荐几部高分动画电影', '想看真实事件改编的电影', '推荐几部恐怖片，越吓人越好',
				'有没有适合全家一起看的电影', '推荐几部高分纪录片', '来点轻松的下饭剧', '想看武侠或古装片',
				'推荐几部欧洲文艺片', '有没有印度高分电影', '推荐几部战争题材电影', '最近评分高的国产剧',
				'推荐几部周星驰的电影', '有什么诺兰导演的作品', '推荐几部成龙的动作片', '想看梁朝伟主演的电影',
				'推荐几部宫崎骏动画', '心情不好，推荐点治愈电影', '适合深夜一个人看的电影', '适合和女朋友一起看的电影',
				'推荐几部看完会思考的电影', '想找一部能哭出来的电影', '类似《流浪地球》的电影', '类似《肖申克的救赎》的电影',
				'类似《狂飙》的国产剧', '推荐几部冷门但好看的电影', '推荐几部高分犯罪美剧', '有没有热门的韩剧推荐',
				'推荐几部经典日剧', '想看高智商对决的电影', '推荐几部赛车题材电影', '有没有关于人工智能的电影',
				'推荐几部太空题材电影', '想看末日灾难片', '推荐几部超级英雄电影', '有没有好看的吸血鬼电影',
				'推荐几部丧尸题材电影', '想看怪兽电影', '推荐几部法庭题材电影', '有没有关于医生题材的剧',
				'推荐几部职场题材电视剧', '想看校园青春电影', '推荐几部谍战剧', '有没有扫黑题材的国产剧',
				'推荐几部历史正剧', '想看人物传记电影', '推荐几部音乐题材电影', '有没有关于美食的电影',
				'推荐几部公路片', '想看海边小镇题材的电影', '推荐几部黑色幽默电影', '有没有反转到最后一秒的电影',
				'推荐几部高分悬疑短片', '想看穿越题材的剧', '推荐几部仙侠剧', '推荐几部玄幻题材电影',
				'有没有好看的神话电影', '推荐几部体育题材电影', '想看拳击题材电影', '推荐几部足球题材电影',
				'有没有关于篮球的电影', '推荐几部高分爱情剧', '想看姐弟恋题材的剧', '推荐几部悬疑爱情片',
				'有没有关于婚姻的电影', '推荐几部家庭伦理剧', '想看关于亲情的电影', '推荐几部关于友情的电影',
				'有没有关于成长的电影', '推荐几部治愈系日剧', '想看轻松搞笑的国产剧', '推荐几部情景喜剧',
				'有没有适合下饭的综艺', '推荐几部高质量国产动画', '想看皮克斯的动画电影', '推荐几部迪士尼动画',
				'有没有关于动物的电影', '推荐几部奇幻冒险电影', '想看魔法题材电影', '推荐几部海盗题材电影',
				'有没有关于寻宝的电影', '推荐几部特工题材电影', '想看劫案题材电影', '推荐几部监狱题材电影',
				'有没有关于复仇的电影', '推荐几部黑帮题材电影', '想看西部片', '推荐几部经典老电影',
				'有没有 90 年代的经典电影', '推荐几部今年新上映的电影', '想看评分 9 分以上的电影', '推荐几部适合二刷的电影'
			];
			var prefixes = [
				'推荐几部', '有没有', '想看', '来点', '求推荐', '推荐点', '有没有好看的',
				'推荐好看的', '想找几部', '找几部', '来几部', '最近有什么好看的',
				'适合周末看的', '评分高的', '值得二刷的', '冷门但好看的', '适合一个人看的',
				'经典一点的', '口碑好的', '节奏快的', '剧情紧凑的'
			];
			var subjects = [
				'科幻片', '悬疑片', '悬疑剧', '喜剧', '喜剧片', '犯罪片', '爱情片', '动作片',
				'动画电影', '恐怖片', '纪录片', '战争片', '港片', '日剧', '韩剧', '美剧',
				'国产剧', '武侠片', '古装剧', '文艺片', '公路片', '灾难片', '传记片', '音乐片',
				'美食电影', '校园片', '职场剧', '谍战剧', '仙侠剧', '奇幻电影', '体育电影', '家庭剧',
				'治愈系电影', '黑色幽默片', '超级英雄电影', '丧尸片', '怪兽电影', '赛车电影', '太空电影', '魔术电影',
				'海盗电影', '监狱电影', '律师剧', '医疗剧', '历史剧', '年代剧', '青春片', '冒险电影',
				'推理片', '犯罪美剧'
			];
			var pool = curated.slice();
			var seen = {};
			for(var i = 0; i < pool.length; i++) seen[pool[i]] = 1;
			for(var p = 0; p < prefixes.length && pool.length < 1000; p++){
				for(var s = 0; s < subjects.length && pool.length < 1000; s++){
					var text = prefixes[p] + subjects[s];
					if(!seen[text]){ seen[text] = 1; pool.push(text); }
				}
			}
			return pool;
		})();

		function randomChips(count){
			var pool = CHIP_POOL.slice();
			for(var i = pool.length - 1; i > 0; i--){
				var j = Math.floor(Math.random() * (i + 1));
				var tmp = pool[i]; pool[i] = pool[j]; pool[j] = tmp;
			}
			return pool.slice(0, count);
		}

		function renderChips(){
			removeChips();
			var wrap = document.createElement('div');
			wrap.id = 'aiRecChips';
			wrap.className = 'ai-rec-chips';
			var list = randomChips(4);
			for(var i = 0; i < list.length; i++){
				(function(text){
					var chip = document.createElement('span');
					chip.className = 'ai-rec-chip';
					chip.innerHTML = esc(text);
					chip.onclick = function(){ sendMessage(text); };
					wrap.appendChild(chip);
				})(list[i]);
			}
			body.appendChild(wrap);
			scrollBottom();
		}

		function removeChips(){
			var chips = document.getElementById('aiRecChips');
			if(chips && chips.parentNode) chips.parentNode.removeChild(chips);
		}

		function showTyping(){
			typingRow = document.createElement('div');
			typingRow.className = 'ai-rec-msg bot';
			var avatar = avatarEl();
			var dots = document.createElement('div');
			dots.className = 'ai-rec-typing';
			dots.innerHTML = '<i></i><i></i><i></i>';
			typingRow.appendChild(avatar);
			typingRow.appendChild(dots);
			body.appendChild(typingRow);
			scrollBottom();
		}

		function hideTyping(){
			if(typingRow && typingRow.parentNode) typingRow.parentNode.removeChild(typingRow);
			typingRow = null;
		}

		function resizeInput(){
			input.style.height = '38px';
			var h = input.scrollHeight;
			if(h > 90) h = 90;
			input.style.height = h + 'px';
		}

		function sendMessage(text, exactTitle){
			text = String(text == null ? '' : text).replace(/^\s+|\s+$/g, '');
			if(text === '' || sending) return;

			sending = true;
			sendBtn.disabled = true;
			removeChips();
			addMsg('user', text);
			input.value = '';
			resizeInput();
			showTyping();

			var payloadHistory = history.slice(-6);
			var payload = 'message=' + encodeURIComponent(text)
				+ '&history=' + encodeURIComponent(JSON.stringify(payloadHistory));
			if(exactTitle) payload += '&exact_title=' + encodeURIComponent(exactTitle);

			var xhr = new XMLHttpRequest();
			xhr.open('POST', apiBase + '?action=chat', true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
			xhr.onreadystatechange = function(){
				if(xhr.readyState !== 4) return;

				hideTyping();
				sending = false;
				sendBtn.disabled = false;

				var data = null;
				try{ data = JSON.parse(xhr.responseText); }catch(e){}

				if(xhr.status === 200 && data && data.ok){
					var row = addMsg('bot', data.reply || '');
					var contentEl = row.getElementsByTagName('div');
					var box = null;
					for(var i = 0; i < contentEl.length; i++){
						if(contentEl[i].className === 'ai-rec-content'){ box = contentEl[i]; break; }
					}
					if(box) addMovies(box, data.movies);
					scrollBottom();
					history.push({role: 'user', content: text});
					history.push({role: 'assistant', content: data.reply || ''});
					if(history.length > 10) history = history.slice(-10);
				}else{
					var errMsg = (data && data.error) ? data.error : '网络开小差了，请稍后再试。';
					addMsg('bot', errMsg);
				}
			};
			xhr.send(payload);
		}

		var hotLoaded = false;

		function loadHot(){
			var xhr = new XMLHttpRequest();
			xhr.open('GET', apiBase + '?action=hot&_t=' + (new Date()).getTime(), true);
			xhr.onreadystatechange = function(){
				if(xhr.readyState !== 4 || xhr.status !== 200) return;
				var data;
				try{ data = JSON.parse(xhr.responseText); }catch(e){ return; }
				if(!data || !data.ok || !data.items || !data.items.length) return;
				renderHot(data.items);
			};
			xhr.send(null);
		}

		function renderHot(items){
			if(document.getElementById('aiRecHot')) return;

			var list = items.slice(0).sort(function(a, b){
				return (b.matched ? 1 : 0) - (a.matched ? 1 : 0);
			}).slice(0, 4);
			if(!list.length) return;

			var wrap = document.createElement('div');
			wrap.id = 'aiRecHot';
			wrap.className = 'ai-rec-hot';

			var head = document.createElement('div');
			head.className = 'ai-rec-hot-title';
			head.innerHTML = FIRE_SVG + '<span>近期热门</span>';
			wrap.appendChild(head);

			var box = document.createElement('div');
			box.className = 'ai-rec-hot-chips';
			for(var i = 0; i < list.length; i++){
				(function(item){
					var chip = document.createElement('span');
					chip.className = 'ai-rec-hot-chip';
					chip.innerHTML = esc('《' + item.title + '》');
					chip.title = item.reason || item.title;
					chip.onclick = function(){
						sendMessage('《' + item.title + '》', item.title);
					};
					box.appendChild(chip);
				})(list[i]);
			}
			wrap.appendChild(box);

			var chips = document.getElementById('aiRecChips');
			if(chips && chips.parentNode === body){
				body.insertBefore(wrap, chips);
			} else {
				body.appendChild(wrap);
			}
		}

		function openPanel(){
			panel.className = 'ai-rec-show';
			launcher.style.display = 'none';
			if(!hotLoaded){
				hotLoaded = true;
				loadHot();
			}
			setTimeout(function(){ try{ input.focus(); }catch(e){} }, 120);
			scrollBottom();
		}

		function closePanel(){
			panel.className = '';
			launcher.style.display = 'flex';
		}

		launcher.onclick = openPanel;
		launcher.onkeydown = function(e){
			e = e || window.event;
			if(e.keyCode === 13 || e.keyCode === 32){ openPanel(); return false; }
		};
		closeBtn.onclick = closePanel;
		refreshBtn.onclick = function(){
			renderChips();
			try{ input.focus(); }catch(e){}
		};
		sendBtn.onclick = function(){ sendMessage(input.value); };

		input.oninput = resizeInput;
		input.onkeydown = function(e){
			e = e || window.event;
			if(e.keyCode === 13 && !e.shiftKey){
				if(e.preventDefault) e.preventDefault();
				sendMessage(input.value);
				return false;
			}
		};

		document.addEventListener('keydown', function(e){
			if((e.keyCode === 27) && panel.className.indexOf('ai-rec-show') !== -1) closePanel();
		}, false);

		// 首次打开时的欢迎语与快捷提问
		addMsg('bot', '你好，我是 AI 推荐官。告诉我你想看的类型、演员或心情，我来帮你挑片。');
		renderChips();
	}
})();