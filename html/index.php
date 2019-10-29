<?php

$dbDir = dirname(__DIR__) . '/db';

$url = urldecode($_GET['url']);

if (isset($_GET['url']) && parse_url($url)) {
    $width = $_GET['w'] ?? 100;
    $char = $_GET['c'] ?? '0';
    $trimLeft = $_GET['tl'] ?? 0;
    $trimRight = $_GET['tr'] ?? 0;
    $trimTop = ($_GET['tt'] ?? 0) + 1;
    $trimBottom = ($_GET['tb'] ?? 0);

    if ($width > 200) {
        $width = 200;
    }

    $sha256Url = hash('sha256', "{$url}#{$width}");

    header('Content-Type: text/plain;charset=UTF-8');
    $shell = "(
        cat ${dbDir}/${sha256Url} \
          || curl -L -A 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:59.0) Gecko/20100101 Firefox/59.0' -sS '${url}' | ansize /dev/stdin ${dbDir}/${sha256Url} ${width} \
      ) \
        | sed 's/m1/m0/g' \
        | sed 's/m0/m${char}/g' \
        | sed 's/^\(\e[^\e]*\)\{${trimLeft}\}//; s/\(\e[^\e]*\)\{${trimRight}\}$//; ' \
        | tail -n +${trimTop} \
        | head -n -${trimBottom} \
        ;
    ";
    exec($shell, $output, $returnValue);

    foreach ($output as $row) {
        echo $row, PHP_EOL;
    }
} else { ?>
<!DOCTYPE html>
<html lang="ja">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, user-scalable=0, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0">
    <!-- Bootstrap -->
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
    <meta name="keywords" content="image,asciiart,convert,webapi,free" />
    <meta name="description" content="It's the web-api for converting free-use images into colored ASCII art for terminals." />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:site" content="@genzouw" />
    <meta name="twitter:creator" content="@genzouw" />
    <meta property="og:url" content="https://img2txt.genzouw.com" />
    <meta property="og:title" content="img2txt" />
    <meta property="og:description" content="'img2txt' is a web API for converting image files into colored ASCII art that can be displayed on Unix terminals." />
    <meta property="og:image" content="https://i.imgur.com/ZZaPoN3l.png" />
    <title>Top - img2txt</title>
    <style>
h3 {
    padding-left: 10px ;
    border-left-width: 5px ;
    border-left-style: solid ;
    border-left-color: #6495ED;
}

pre {
    background: #f8f8f8;
    border: 1px solid lightgray;
    padding: 0.1em 1.0em;
}
    </style>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=UA-41583079-16"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', 'UA-41583079-16');
    </script>
    <script data-ad-client="ca-pub-6278393724888948" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
  </head>

  <body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
      <h1 style="font-size: 1.5em; margin: 0;"><a class="navbar-brand" href="/">img2txt</a></h1>
    </nav>

    <div class="container-fluid">
      <div class="row justify-content-center">
        <div class="col-auto">
          <h2>What is "img2txt"?</h2>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <p>"img2txt" is a free web API. You can do the following:</p>
        </div>

        <ul>
          <li>Converts an image file into colored ASCII art (text) that can be displayed on Unix terminals.</li>
          <li>You can specify the output text magnification from 1 to 200%.</li>
          <li>You can trim the output text any characters at a time.</li>
          <li>You can specify a character that make up the ASCII art that is output.</li>
        </ul>
      </div>

      <div class="row justify-content-center">
        <div class="col-auto">
          <h2> How do you use it? </ h2>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <p>It is easy to use the <strong>"curl"</strong> command. Call WebAPI with the <strong>"curl"</strong> command. </p>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <h3> WebAPI endpoint URL </h3>
        </div>
      </div>

      <div class="row">
        <div class="col-12">
          <p><strong style="color: green;">https://img2txt.genzouw.com</strong></p>
        </div>
      </div>

      <div class="row">
        <div class="col-auto">
          <h3><em style="color: blue;">"url"</em> query string <strong style="color:red;">Required</strong> )</h3>
        </div>
      </div>

      <div class="row">
        <div class="col-auto">
          <p>Specify the URL of the image file you want to convert. </p>
          <p> This query string is required. </ p>
          <pre class="line-numbers"><code class="language-bash">
# If you want to convert the following image file URL to ASCII art
# * https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png
$ curl -sS 'https://img2txt.genzouw.com?url=https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png'
          </code></pre>
        </div>
      </div>

      <div class="row">
        <div class="col-auto">
          <p>The following ASCII art should be displayed in the terminal.</p>

          <blockquote class="imgur-embed-pub" lang="en" data-id="a/cHZJiob" data-context="false" ><a href="//imgur.com/a/cHZJiob"></a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>
        </div>
      </div>

      <div class="row">
        <div class="col-auto">
          <h3><em style="color: blue;">"tl"</em> / <em style="color: blue;">"tr"</em> / <em style="color: blue;">"tt"</em> / <em style="color: blue;">"tb"</em> query string</h3>
        </div>
      </div>

      <div class="row">
        <div class="col-auto">
          <p>You can also trim the output.</p>

          <p><em style="color: blue;">"tl"</em> query string : Specify a numeric value. Remove N columns from the left.</p>
          <p><em style="color: blue;">"tr"</em> query string : Specify a numeric value. Remove N columns from the right.</p>
          <p><em style="color: blue;">"tt"</em> query string : Specify a numeric value. Remove N lines from the top.</p>
          <p><em style="color: blue;">"tb"</em> query string : Specify a numeric value. Remove N lines from the bottom.</p>

          <pre class="line-numbers"><code class="language-bash">
# Delete 10 columns from the left
$ curl -sS 'https://img2txt.genzouw.com?url=https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png&tl=10'
          </code></pre>

          <blockquote class="imgur-embed-pub" lang="en" data-id="a/oAdnOzH"><a href="//imgur.com/a/oAdnOzH"></a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>

          <pre class="line-numbers"><code class="language-bash">
# Delete 10 rows and 10 columns
$ curl -sS 'https://img2txt.genzouw.com?url=https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png&tl=10&tr=10&tt=10&tb=10'
          </code></pre>

          <blockquote class="imgur-embed-pub" lang="en" data-id="a/6REf0kp" data-context="false" ><a href="//imgur.com/a/6REf0kp"></a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>
        </div>
      </div>

      <div class="row">
        <div class="col-auto">
          <h3><em style="color: blue;">"w"</em> query string</h3>
        </div>
      </div>

      <div class="row">
        <div class="col-auto">
          <p> You can also specify the “Zoom” of the ASCII art to be output. </p>

          <p>You can specify a value between 1 and 200% for the magnification.</p>

          <pre class="line-numbers"><code class="language-bash">
# Output in half size
$ curl -sS 'https://img2txt.genzouw.com?url=https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png&w=50'
          </code></pre>

          <blockquote class="imgur-embed-pub" lang="en" data-id="a/JOzy2lK" data-context="false" ><a href="//imgur.com/a/JOzy2lK"></a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>


          <pre class="line-numbers"><code class="language-bash">
# Output in 1.2 times the size
$ curl -sS 'https://img2txt.genzouw.com?url=https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png&w=120'
          </code></pre>

          <blockquote class="imgur-embed-pub" lang="en" data-id="a/QjSxQ70" data-context="false" ><a href="//imgur.com/a/QjSxQ70"></a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>
        </div>
      </div>

      <div class="row">
        <div class="col-auto">
          <h3><em style="color: blue;">"c"</em> query string</h3>
        </div>
      </div>

      <div class="row">
        <div class="col-auto">
          <p> By default, "@" is used for the ASCII art string, but you can use any character with the "c" query string. </ p>

          <pre class="line-numbers"><code class="language-bash">
# Try using the "?" Character
$ curl -sS 'https://img2txt.genzouw.com?url=https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png&c=?'
          </code></pre>

          <blockquote class="imgur-embed-pub" lang="en" data-id="a/zD7ioZx" data-context="false" ><a href="//imgur.com/a/zD7ioZx"></a></blockquote><script async src="//s.imgur.com/min/embed.js" charset="utf-8"></script>

    </div>


    <footer class="page-footer font-small fixed-bottom bg-dark text-light">
      <div class="footer-copyright text-center py-3">
      <p>Copyright © 2019-2019 <a href="https://genzouw.com">genzouw</a> All Rights Reserved.</p>
      <p>( twitter:<a href="https://twitter.com/genzouw">@genzouw</a> , facebook:<a href="https://www.facebook.com/genzouw">genzouw</a>, mailto:<a href="mailto:genzouw@gmail.com">genzouw@gmail.com</a> )</p>
      </div>
    </footer>

    <!-- Latest compiled and minified JavaScript -->
    <script src="https://code.jquery.com/jquery-3.3.1.min.js" integrity="sha256-FgpCb/KJQlLNfOu91ta32o/NMZxltwRo8QtmkMRdAu8=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh3U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/vue"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.17.1/components/prism-bash.min.js" integrity="sha256-5Ij4Bu8ihjPbr4L8g80cuyGgEoxw6g8tFBPJn95PV7w=" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.17.1/plugins/toolbar/prism-toolbar.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
  </body>
</html>
<?php }
