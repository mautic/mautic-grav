# Mautic - Grav Plugin

This is [Grav CMS](http://getgrav.org) plugin that helps you configure [Mautic](https://mautic.org) tracking and converts markdown "links" and shortcodes into HTML that embeds Mautic content on your Grav page.

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
url: 'https://mautic.loc'   # Mautic base URL
```

If you need to change any value, then the best process is to copy the [mautic.yaml](mautic.yaml) file into your `users/config/plugins/` folder (create it if it doesn't exist), and then modify there.  This will override the default settings.

# Usage

## Mautic tracking

Tracking JS works right after you enable the plugin, enable tracking, insert the Base URL and save the plugin. After this, the plugin will insert JS into the head of your site from your Mautic instance. You can check HTML source code (CTRL + U) of your Grav website to make sure the plugin works. You should be able to find something like this:

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

## Mautic content

Mautic content is included as shortcodes, identified by two parameters `type`
and `id`, where `type` specifies the content type, and `id` specifies one
particular content element delivered by Mautic. The following table lists the
available content types, as well as their associated IDs:

| Type      | ID                                                                                                        |
|-----------|-----------------------------------------------------------------------------------------------------------|
| `form`    | The ID of the form (from Mautic "Components/Forms" overview table)                                        |
| `content` | The DWC slot name (from Mautic "Components/Dynamic Content" element in edit mode as "Requested slot name")|
| `focus`   | The ID of the focus item (from Mautic "Channels/Focus Items" overview table)                              |
| `asset`   | The ID of the asset. Additionally, the asset's *alias* is needed, appended to the ID by a colon (`:`)     |

The general syntax is:

```
[mautic type="<type>" id="<id>"][/mautic]
```

The following sections provide examples on the detailed usage for each content
element.

### Forms

There are two options to include Mautic forms into Grav pages: shortcodes and
Markdown links. The reason is that the shortcode parser used by this plugin is
not yet mature enough to support nested shortcodes.

1. Via Shortcode

    To use this, we simply include the following:
    
    ```
    [mautic type="form" id="<FORM_ID>"][/mautic]
    ```
    
    Example: `[mautic type="form" id="8"][/mautic]` will load Mautic form with ID = 8.
    
    This code snippet will be converted into the following:
    
    ```
    <script type="text/javascript" src="https://mautic.loc/form/generate.js?id=8"></script>
    ```

2. Via Markdown link

    There is a second option to include a form into a Grav page, which is to simply
    include a Mautic Form ID in markdown link such as:
    
    ```
    [plugin:mautic](FORM_ID)
    ```
    
    Example: `[plugin:mautic](8)` will load Mautic form with ID = 8.
    
    This alternative form of including Mautic forms via link is very useful to
    combine embedded forms with dynamic web content directives. One example use case
    would be to only show a newsletter signup form if a visitor has not already
    signed up:
    
    ```
    [mautic type="content" id="subscribed2newsletter"]
      [plugin:mautic](2)
    [/mautic]
    ```
    
    The above code snippet works as follows: If a user is already signed up, a
    message is displayed like "You are already signed up" via dynamic web content.
    Otherwise, if a user is not already signed up, a form is shown for the visitor
    to enter her data.

### Dynamic Web Content (DWC)

To use this, simply include the Mautic dynamic content shortcode in your content.

```
[mautic type="content" id="slot_name"]Default content to show when an unknown contact views this slot.[/mautic]
```

This code snippet will be converted into the following:

```
<div data-slot="dwc" data-param-slot-name="slot_name">Default content to show when an unknown contact views this slot.</div>
```

### Focus Items

To add focus items into a page, we simply add the following shortcode into the
content of a Grav page:


```
[mautic type="focus" id="ITEM_ID"][/mautic]
```

For example:

```
[mautic type="focus" id="1"][/mautic]
```

This will be converted into the following

```
<script type="text/javascript" src="https://mautic.loc/focus/1.js" type="text/javascript" charset="utf-8" async="async"></script>
```

### Assets

Assets are downloadable items which can be tracked with Mautic. Typically,
assets are provided when a website user fills out a form. When assets are
provided after filling a form, this has to be set up in Mautic (and the form can
be included using this plugin).

Assets can also be provided using direct links; the asset download will be
tracked by Mautic, but not in exchange for some form data in this case. For
assets downloadable via direct links, we add the following shortcode into the
content of a Grav page:

```
[mautic type="asset" id="<ID>:<ALIAS>"]
  Link text
[/mautic]
```

The shortcode to insert asset links expects two parameters; Besides the usual
`type` parameter, for the `id` parameter, we also need to provide the *alias* of
the asset, joined with the asset's ID by a colon (`:`).  Both the ID and the
alias can be found in the asset's details in Mautic. The text between the
opening and closing shortcode tags is the text of the link that is generated to
download the asset.

The following example shows a shortcode that generates a link for donwloading a
logo:

```
[mautic type="asset" id="1:logopng"]
  Download our logo
[/mautic]
```

The above example converts into the following HTML:

```
<a href="https://mautic.loc/asset/1:logopng">Download our logo</a>
```

> :warning: **WARNING:** A separate `alias` parameter is still supported, but
> deprecated since 1.5.1
>
> ```
> [mautic type="asset" id="1" alias="logopng"]
>   Download our logo
> [/mautic]
> ```
