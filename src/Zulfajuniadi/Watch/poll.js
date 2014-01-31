;(function(){
  function microAjax(B,A){this.bindFunction=function(E,D){return function(){return E.apply(D,[D])}};this.stateChange=function(D){if(this.request.readyState==4){this.callbackFunction(this.request.responseText)}};this.getRequest=function(){if(window.ActiveXObject){return new ActiveXObject("Microsoft.XMLHTTP")}else{if(window.XMLHttpRequest){return new XMLHttpRequest()}}return false};this.postBody=(arguments[2]||"");this.callbackFunction=A;this.url=B;this.request=this.getRequest();if(this.request){var C=this.request;C.onreadystatechange=this.bindFunction(this.stateChange,this);if(this.postBody!==""){C.open("POST",B,true);C.setRequestHeader("X-Requested-With","XMLHttpRequest");C.setRequestHeader("Content-type","application/x-www-form-urlencoded");C.setRequestHeader("Connection","close")}else{C.open("GET",B,true)}C.send(this.postBody)}};
  var timestamp = (new Date()).toISOString();
  var js = css = [], origin = window.location.origin;
  var pollscript = document.getElementById('pollscript');
  var timeout = (pollscript.dataset.timeout || 3000);
  console.log('Watcher started. Interval set at: ' + timeout + 'ms.');
  function getResources() {
    js = [];
    css = [];
    var jsScripts = document.querySelectorAll('script');
    var cssScripts = document.querySelectorAll('link[href]');
    var script, stylesheet, href, src;
    for (var i = 0; i < jsScripts.length; ++i) {
      script = jsScripts[i];
      if(script.src && script.src !== origin + '/watchpoller.js' && script.src.indexOf(origin) > -1) {
        src = script.src.split(origin)[1];
        js.push(src);
      }
    }
    for (var i = 0; i < cssScripts.length; ++i) {
      stylesheet = cssScripts[i];
      if(stylesheet.href && stylesheet.href.indexOf(origin) > -1) {
        href = stylesheet.href.split(origin)[1];
        css.push(href);
      }
    }
    loop();
  }
  function loop() {
    setTimeout(function(){
      var paramObj = {
        timestamp: timestamp,
        js: js,
        css: css
      };
      if(pollscript) {
        var dataset = pollscript.dataset;
        var keys = Object.keys(dataset);
        for (var i = 0; i < keys.length; ++i) {
          var key = keys[i];
          paramObj[key] = dataset[key];
        };
      }
      var params = JSON.stringify(paramObj);
      microAjax('/_watcher?query=' + params, function(res){
        try {
          res = JSON.parse(res);
          if(res.do === 'RELOAD')
            window.location.reload(true);
        } catch(e) {
          console.error(res);
        }
        loop();
      });
    }, timeout);
  }
  getResources();
})();