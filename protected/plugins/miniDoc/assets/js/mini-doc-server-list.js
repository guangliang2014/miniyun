$(document).ready(function(){$("tr").mouseover(function(){$(this).addClass("over")}).mouseout(function(){$(this).removeClass("over")})});var getVal=function(b,a){$("#id").val(b);$("#ip").val($("#ip"+b).text());$("#port").val(a)};var modifyStatus=function(a){$("#get-id").val(a)};var serverStatus=function(c,b,a){$("#status").val(a);$("#modify-run-status-url").val("http://"+$("#ip"+c).text()+":"+b+"/hello")};var ok=function(){$("#fileLoading").css("display","block");$("#my-modal-label").css("display","none");var b=$("#get-id").val();var a=$("#status").val();Modify.modifyRunStatus(b,a)};var Modify={modifyRunStatus:function(c,b){if(b=="0"){var a=$("#modify-run-status-url").val();$.ajax({timeout:3000,url:a,type:"GET",success:function(d){Modify.runStatus(d,c)},error:Modify.onError})}else{$.ajax({url:$("#modify-run-url").val(),type:"POST",data:{id:c,status:"0"},success:Modify.onSuccess1,error:Modify.onError})}},runStatus:function(a,b){$.ajax({url:$("#modify-run-url").val(),type:"POST",data:{status:"1",id:b},success:Modify.onSuccess,error:Modify.onError})},onSuccess:function(a){$("#fileLoading").css("display","none");$("#my-modal-label").css("display","none");$("#connected-tip").css("display","block");$(".sureToChange").css("display","none");$(".btn-default").text($("#sure").val())},onSuccess1:function(a){$("#fileLoading").css("display","none");$("#my-modal-label").css("display","none");$("#connected-tip1").css("display","block");$(".sureToChange").css("display","none");$(".btn-default").text($("#sure").val())},onError:function(b){try{$("#my-modal-label").css("display","none");$("#fileLoading").css("display","none");$("#disconnect-tip").css("display","block");$(".sureToChange").css("display","none");$(".btn-default").text($("#sure").val())}catch(a){}}};var cancel=function(){location.reload()};