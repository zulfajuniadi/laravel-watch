LARAVEL WATCH
=============

#What is this?

This package will reload your browser anytime an important file in your application has changed. By default it watches the controllers, models, views folder and any css / js files loaded inside the view.

##Why?

If you've been developing all these while without live reload, you're missing out on something great!

##Then why not just use LiveReload?

1. Commercial one has to be paid.
2. The free ones often requires NodeJS, and most of the time bundled together with Bower, Grunt, etc, etc. Thus making it quite intimidating for junior developers.
3. There is not a package like this (that I know of) yet - especially for Laravel.

#INSTALLATION

```
composer require zulfajuniadi/laravel-watch dev-master
```

#SETUP

1. In /app/config/app.php, providers array, add: ``'Zulfajuniadi\Watch\WatchServiceProvider'``
2. In your footer layout / template / view add the following: ```{{HTML::watcherScript(1000)}}```
3. In the terminal, in your root project directory run: `artisan watch:enable`
4. That's it. Whenever you save a file, the browser should automatically reload reflecting the changes.

