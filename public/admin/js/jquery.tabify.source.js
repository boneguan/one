<!DOCTYPE html>
<html>
    <head>
        <title>无法找到资源。</title>
        <meta name="viewport" content="width=device-width" />
        <style>
         body {font-family:"Verdana";font-weight:normal;font-size: .7em;color:black;} 
         p {font-family:"Verdana";font-weight:normal;color:black;margin-top: -5px}
         b {font-family:"Verdana";font-weight:bold;color:black;margin-top: -5px}
         H1 { font-family:"Verdana";font-weight:normal;font-size:18pt;color:red }
         H2 { font-family:"Verdana";font-weight:normal;font-size:14pt;color:maroon }
         pre {font-family:"Consolas","Lucida Console",Monospace;font-size:11pt;margin:0;padding:0.5em;line-height:14pt}
         .marker {font-weight: bold; color: black;text-decoration: none;}
         .version {color: gray;}
         .error {margin-bottom: 10px;}
         .expandable { text-decoration:underline; font-weight:bold; color:navy; cursor:hand; }
         @media screen and (max-width: 639px) {
          pre { width: 440px; overflow: auto; white-space: pre-wrap; word-wrap: break-word; }
         }
         @media screen and (max-width: 479px) {
          pre { width: 280px; }
         }
        </style>
    </head>

    <body bgcolor="white">

            <span><H1>“/”应用程序中的服务器错误。<hr width=100% size=1 color=silver></H1>

            <h2> <i>无法找到资源。</i> </h2></span>

            <font face="Arial, Helvetica, Geneva, SunSans-Regular, sans-serif ">

            <b> 说明: </b>HTTP 404。您正在查找的资源(或者它的一个依赖项)可能已被移除，或其名称已更改，或暂时不可用。请检查以下 URL 并确保其拼写正确。
            <br><br>

            <b> 请求的 URL: </b>/Scripts/Tabs/jquery.tabify.source.js<br><br>

            <hr width=100% size=1 color=silver>

            <b>版本信息:</b>&nbsp;Microsoft .NET Framework 版本:4.0.30319; ASP.NET 版本:4.0.30319.34249

            </font>

    </body>
</html>
<!-- 
[HttpException]: 未找到路径“/Scripts/Tabs/jquery.tabify.source.js”的控制器或该控制器未实现 IController。
   在 System.Web.Mvc.DefaultControllerFactory.GetControllerInstance(RequestContext requestContext, Type controllerType)
   在 System.Web.Mvc.DefaultControllerFactory.CreateController(RequestContext requestContext, String controllerName)
   在 System.Web.Mvc.MvcHandler.ProcessRequestInit(HttpContextBase httpContext, IController& controller, IControllerFactory& factory)
   在 System.Web.Mvc.MvcHandler.<>c__DisplayClass6.<BeginProcessRequest>b__2()
   在 System.Web.Mvc.SecurityUtil.<>c__DisplayClassb`1.<ProcessInApplicationTrust>b__a()
   在 System.Web.Mvc.SecurityUtil.ProcessInApplicationTrust[TResult](Func`1 func)
   在 System.Web.HttpApplication.CallHandlerExecutionStep.System.Web.HttpApplication.IExecutionStep.Execute()
   在 System.Web.HttpApplication.ExecuteStep(IExecutionStep step, Boolean& completedSynchronously)
--><!-- 
此错误页可能包含敏感信息，因为 ASP.NET 通过 &lt;customErrors mode="Off"/&gt; 被配置为显示详细错误消息。请考虑在生产环境中使用 &lt;customErrors mode="On"/&gt; 或 &lt;customErrors mode="RemoteOnly"/&gt;。-->