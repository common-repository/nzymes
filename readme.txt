=== Nzymes ===
Contributors: aercolino
Donate link: https://www.paypal.me/AndreaErcolino
Tags: inject, custom fields, attributes, post, author, php, code, enzymes
Requires at least: 4.7
Tested up to: 4.7.5
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Boost your posts with Nzymes injections. Safely use PHP in posts' title, excerpt, and content. WordPress 4.7+ PHP 5.6+



== Description ==

*Your Problem*

* You want to do something inside a post, but a plugin might not exist.

*Nzymes Solution*

* Insert PHP code into posts' content easily and safely.
* Program features by yourself or trust some of your blog's authors.


= Example =

When citing authors in a post, you'd like to show the number of posts they published. Maybe there is a plugin for that, maybe there is not. Anyway, how difficult to code would it be? Quite easy, actually, given that WordPress has a function to do exactly that (*count_user_posts*).

With Nzymes, you could cite authors like this

> As you know, {[ =john= | @my.cite(1) ]} and I are very good friends.

Which could be shown like this

> As you know, John (42 posts) and I are very good friends.

With Nzymes, many things are just a couple of PHP lines away.

* Why not display "(42 posts)" in red?
* Why not link "John" to John's posts?


= At a glance =

Nzymes injections are expressions like this: {[ enzyme-1 | enzyme-2 | … enzyme-N ]}

* Nzymes automatically filters title, excerpt, and content of a post looking for injections.
* For each found injection, it orderly replaces each enzyme with its value, left to right.
* Then it replaces the value of the last enzyme to the whole injection.

Manual: https://github.com/aercolino/nzymes/blob/master/nzymes-manual.md


= Reach out =

* Community: https://www.facebook.com/NzymesPlugin/
* Download: https://wordpress.org/plugins/nzymes/
* Develop: https://github.com/aercolino/nzymes



== Frequently Asked Questions ==


= What is an enzyme? =

Any of the following:

1. A string or an unsigned integer.
1. A locator of an attribute or a custom field of a post or a post's author.


= What is the value of an enzyme? =

Any of the following:

1. Its literal value.
1. The value referenced by the locator (transclusion).
1. The value returned by evaluating the PHP code referenced by the locator (execution).


= Is Nzymes secure? =

Nzymes enforces a rich set of capabilities and roles.

1. After activating Nzymes, only administrators have all the capabilities.
1. To allow authors to use Nzymes, administrators may explicitly assign roles as they see fit.

For example, to execute code of some author into a post of some injector, first of all the author must be able to `create_dynamic_custom_fields`. Then, additionally, either the injector and the author are the same person or the injector can `use_others_custom_fields` and the author can `share_dynamic_custom_fields`.


= OK, where's the manual? =

* https://github.com/aercolino/nzymes/blob/master/nzymes-manual.md


= Does Nzymes replace Enzymes? =

Yes! However, if you currently use [Enzymes](https://wordpress.org/plugins/enzymes/), then both can coexist in your blog. Nzymes is much easier to use and it's also much more powerful than Enzymes. Unfortunately, sometimes they are incompatible on the same post. Therefore, Nzymes will only process enzymes injected in posts created after its installation. See the manual to know how you can bend this rule.



== Installation ==

1. Upload the zip file.
1. Activate.



== Changelog ==

= 1.0.0 =

First version.



== Screenshots ==

1. Example. Unusual words. Picture 1/4. Reading. Nzymes ON.
2. Example. Unusual words. Picture 2/4. Reading. Nzymes OFF.
3. Example. Unusual words. Picture 3/4. Writing. Content.
4. Example. Unusual words. Picture 4/4. Writing. Custom fields.
5. Settings. See the manual.
