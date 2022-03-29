# Mautic - Grav Plugin

This is [Grav CMS](http://getgrav.org) plugin that helps you configure [Mautic](https://mautic.org) tracking and converts markdown "links" into Mautic Form Embed code.

# Installation

Installing the Mautic - Grav plugin can be done in one of two ways.

## GPM Installation (Preferred)

The simplest way to install this plugin is via the [Grav Package Manager (GPM)](http://learn.getgrav.org/advanced/grav-gpm) through your system's Terminal (also called the command line).  From the root of your Grav install type:

    bin/gpm install mautic

This will install the Mautic - Grav plugin into your `/user/plugins` directory within Grav. Its files can be found under `/your/site/grav/user/plugins/mautic`.

## Manual Installation

To install this plugin, just [download](https://github.com/mautic/mautic-grav/archive/master.zip) the zip version of this repository and unzip it under `/your/site/grav/user/plugins`. Then, rename the folder to `mautic`.

You should now have all the plugin files under

    /your/site/grav/user/plugins/mautic

# Config Defaults

```
enabled: true               # Global enable/disable the entire plugin
tracking: false             # Enable/Disable tracking
url: 'http://mautic.loc'    # Mautic base URL
```

If you need to change any value, then the best process is to copy the [mautic.yaml](mautic.yaml) file into your `users/config/plugins/` folder (create it if it doesn't exist), and then modify there.  This will override the default settings.

# Usage

## Mautic tracking

Tracking JS works right after you enable the plugin, insert the Base URL and save the plugin (you also have to enable tracking in the configuration settings). After this, the plugin will insert JS into the head of your site from your Mautic instance. You can check HTML source code (CTRL + U) of your Grav website to make sure the plugin works. You should be able to find something like this:

```
<script>
    (function(w,d,t,u,n,a,m){w['MauticTrackingObject']=n;
        w[n]=w[n]||function(){(w[n].q=w[n].q||[]).push(arguments)},a=d.createElement(t),
        m=d.getElementsByTagName(t)[0];a.async=1;a.src=u;m.parentNode.insertBefore(a,m)
    })(window,document,'script','{$mauticBaseUrl}/mtc.js','mt');

    mt('send', 'pageview');
</script>
```

There will be probably longer URL query string at the end of the tracking image URL. It is encoded additional data about the page (title, url, referrer).

The plugin allows to disable tracking, which allows you to control the injection of tracking code via a (3rd-party) consent manager.

## Mautic Form Embed

To use this plugin you simply need to include a Mautic Form ID in markdown link such as:

```
[plugin:mautic](FORM_ID)
```

Example: `[plugin:mautic](8)` will load Mautic form with ID = 8.

This code snippet will be converted into the following:

```
<script type="text/javascript" src="http://yourmautic.com/form/generate.js?id=8"></script>
```

## Mautic Dynamic Content Embed

To use this, simply include the Mautic dynamic content shortcode in your content.

```
[mautic type="content" slot="slot_name"]Default content to show when an unknown contact views this slot.[/mautic]
```

This code snippet will be converted into the following:

```
<div data-slot="dwc" data-param-slot-name="slot_name">Default content to show when an unknown contact views this slot.</div>
```

## Mautic Focus Items

To add focus items into a page, we simply add the following shortcode into the
content of a Grav page:


```
[mautic type="focus" item="ITEM_ID"][/mautic]
```

For example:

```
[mautic type="focus" item="1"][/mautic]
```

This will be converted into the following

```
<script type="text/javascript" src="http://yourmautic.com/focus/1.js" type="text/javascript" charset="utf-8" async="async"></script>
```
