;(function(){
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
  function lastModified(url) {
    try {
      var req=new XMLHttpRequest();
      req.open("GET", url, false);
      req.send(null);
      if(req.status === 200){
          if(req.response && req.response === 'RELOAD') {
            window.location.reload('true');
          }
          return req.response;
      }
      else return false;
    } catch(er) {
      return er.message;
    }
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
      lastModified('/_watcher?query=' + params);
      loop();
    }, timeout);
  }
  getResources();
})();