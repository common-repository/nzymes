# Nzymes


Nzymes is a WordPress plugin for injecting [enzymes](https://en.wikipedia.org/wiki/Enzyme) into a post. Nzymes automatically filters title, excerpt and content of any post type, looking for injections. 

The [Hello, World!](https://en.wikipedia.org/wiki/%22Hello,_World!%22_program) injection could be: 

> Nzymes says "{[ =Hello, World!= ]}" to everybody.

which will render to: 

> Nzymes says "Hello, World!" to everybody.

An injection is always an expression like this: `{[ enzyme-1 | enzyme-2 | ... enzyme-N ]}`. Nzymes orderly computes the result of each enzyme of an injection and replaces the result of the last enzyme to the whole injection.

An enzyme has an origin (itself, post, and author), a form (literal, attribute, and custom field), and a behaviour (transclusion and execution). By combining these characteristics together, Nzymes allows you to greatly improve your posts.


## Part 1: Introduction

### Injections belong to the visual editor

Nzymes' syntax comes directly from Enzymes, a similar plugin that I wrote many years ago, and was meant to do mostly the same things. I chose Enzymes' syntax so that I could write my expressions without switching from the visual to the text editor, which is sometimes needed to make WordPress literally treat what you type (it uses to convert quotes for example), and I consider it very annoying.

Of course you can use whichever editor you like.


### Injections and other expressions

Nzymes doesn't assume anything about what you write in your posts. If some text appears in between `{[` and `]}` Nzymes will try to see if that's an injection or not. If it is, Nzymes will replace all of it (including brackets) with the final result. If it is not, Nzymes will just ignore it. Notice that that means that you'll see that expression as you wrote it in the post (including brackets).


### Syntax of injections

`{[ enzyme-1 | enzyme-2 | ... enzyme-N ]}`

* An injection is delimited by opening `{[` and closing `]}` brackets.
* The inside of an injection is a list of one or more enzymes, separated by a vertical bar.
* Nzymes processes each enzyme of the list in turn, left to write.
* The processing consists in replacing an enzyme with its value.
* Some enzymes eat up a number of previous values.
* Finally, Nzymes replaces all the injection with the value of its last enzyme.


### Syntax of enzymes

--
#### Literals

* `<non negative integer>`
* `=<string>=`

##### Examples

* `12`
* `=Where do you want to go today?=`

--
#### Locators

* `<post identifier>:<attribute name>`
* `<post identifier>.<custom field name>`
* `<post identifier>/author:<attribute name>`
* `<post identifier>/author.<custom field name>`

##### Examples

* post identifier: `23`, `@hello-world`, `<nothing>`
* attribute name: `post_status`, `user_email`
* custom field name: `34`, `anything`, `フェイルセーフ`


### Examples of injections and enzymes

In these examples *something in italics* shows how Nzymes sees it.

--
#### {[ =5 €= | 123.locale(1) ]}

1. this injection has two enzymes
1. enzyme-1 is a literal string and its value will be replaced to itself.
1. enzyme-2 is a locator for the custom field whose name is `locale`, which belongs to the post whose ID is `123`, and its value will be replaced to itself and `1` value before.

##### Processing

1. Nzymes replaces enzyme-1 with its value: 

    | State |
    |---|
    | *"5 €"* |
    | 123.locale(1) |

1. Nzymes replaces enzyme-2 with its value: 

    1. Nzymes hands to it `1` value before: 

        | State |
        |---|
        | *123.locale("5 €")* |

    1. Nzymes evaluates the code stored in the custom field, which converts currency from EUR to the currency of the reader (here it would be USD): 

        | State |
        |---|
        | *"$ 5.49"* |

##### Result

> $ 5.49

--
#### {[ =john= | @wp.comments-count(1) | @cit.comments | @ui.render(2) ]}

1. this injection has 4 enzymes
1. enzyme-1 is a literal string and its value will be replaced to itself.
1. enzyme-2 is a locator for the custom field whose name is `comments-count`, which belongs to the post whose slug is `wp`, and its value will be replaced to itself and `1` value before.
1. enzyme-3 is a locator for the custom field whose name is `comments`, which belongs to the post whose slug is `cit`, and its value will be replaced to itself.
1. enzyme-4 is a locator for the custom field whose name is `render`, which belongs to the post whose slug is `ui`, and its value will be replaced to itself and `2` values before.

##### Processing

1. Nzymes replaces enzyme-1 with its value: 

    | State |
    |---|
    | *"john"* |
    | @wp.comments-count(1) |
    | @cit.comments |
    | @ui.render(2) |

1. Nzymes replaces enzyme-2 with its value: 

    1. Nzymes hands to it `1` value before:

        | State |
        |---|
        | *@wp.comments-count("john")* |
        | @cit.comments |
        | @ui.render(2) |

    1. Nzymes evaluates the code stored in the custom field, which gets the number of comments posted by John:

        | State |
        |---|
        | *{count: 42, unit: "comment", author: "john"}* |
        | @cit.comments |
        | @ui.render(2) |

1. Nzymes replaces enzyme-3 with its value: 

    | State |
    |---|
    | *{count: 42, unit: "comment", author: "john"}* |
    | *":author (`<span style='color: red'>`:count :plural(unit,count)`</span>`"* |
    | @ui.render(2) |

1. Nzymes replaces enzyme-4 with its value: 

    1. Nzymes hands to it `2` values before: 

        | State |
        |---|
        | *@ui.render( {count: 42, unit: "comment", author: "john"}, "`<strong>`:author`</strong>` (:count :plural(unit,count)" )* |

    1. Nzymes evaluates the code stored in the custom field, which (in this case) replaces data to placeholders into a template:

        | State |
        |---|
        | *"`<strong>`John`</strong>` (42 comments)"* |


##### Result

> **John** (42 comments)


### Injections are fail-safe

If Nzymes gets an error while processing an injection, the enzyme causing the error will be replaced by a `null` value, but Nzymes will keep processing the rest of the injection as usual.

If Nzymes gets an error while evaluating the code stored in a custom field, you'll see the error in the JavaScript console of your browser.

Thus, if you see an injection which Nzymes didn't process at all, it means you mistook the syntax somewhere. But if you see nothing, then there could be an error. If the console shows an error, try to understand and fix it. If the console doesn't show any error, it means that you may have an access error. 

Most common reasons for access errors: 

* a locator references something that doesn't exist, 
* you tried to do something which is forbidden to you.


**Examples**

* `12 {[ .not-a-custom-field | 34 ]} 56` produces `12 34 56` without any error in the JS console.
* `12 {[ .not-a-custom-field() | 34 ]} 56` produces `12 34 56` with an error in the JS console: 

    * `Code to execute must be a string: NULL given.`


### Side effects

Each Nzymes injection follows one of three patterns according to the type of the last enzyme:

* `{[ … | <literal enzyme> ]} –> <literal value>`
* `{[ … | <static enzyme> ]} –> <referred value>`
* `{[ … | <dynamic enzyme> ]} –> <returned value>`

Notice that even if the final value of the first two patterns only depends on the value of the last enzyme, no shortcut is ever taking place. Each and every enzyme in the injection is processed in turn, from left to right. This is relevant for side effects that the enzymes before the last one may be causing.

For example, if you want to silence an injection, you only need to end it with an empty string: `{[ … | == ]}`. However, remember that last dynamic enzymes returning `null` are effectively silencing the injection too, no need to use an empty string.


### Escaping injections

If you want to write about Nzymes injections, while also having the Nzymes plugin actively filtering the content of your posts, you need a way to tell Nzymes that it has to ignore some injections. You do it by starting an injection with two braces instead of one: `{{[`. When Nzymes finds an escaped injection, it removes the first brace and displays the rest, without any further processing.

**Example**

All WordPress blogs have a `hello-world` post. 

* If you inject it like `{[ @hello-world:post_title ]}` 

    you'll see `Hello, World!`
    
* but you need to inject it like `{{[ @hello-world:post_title ]}` 

    to see `{[ @hello-world:post_title ]}`.

Notice that Nzymes always converts `{{[` to `{[`, even if those characters don't really start an injection. That means that you cannot write `{{[` in your post and expect to see `{{[` in the browser. In other words, if you want to see N braces before a bracket you need to write (N+1) braces before that bracket.


### Roles and Capabilities

Nzymes supports many (secondary) roles and capabilities so that it works only for those users with the right access.

Capabilities are added to WordPress on plugin activation and removed on deactivation. By default, administrators are granted all capabilities.

Capabilities are checked each time an injection is processed, while they are never checked when an injection or a custom field are added to a post. 

* Thus, users can create all injections they want but Nzymes will never process some enzymes if such users do not have all the needed capabilities to use, create, and share those enzymes. For example, a user with only the *Nzymes User* role can create a dynamic custom field (i.e. a custom field with PHP code) and inject it into her posts, but nobody will ever see any result, because Nzymes will always bypass evaluation and force `null` as the result.

* Additionally, keep in mind that capabilities must be in place when posts are shown, as opposed to when posts are created. Thus, if an administrator removes a user from a role, the injections and the enzymes she created won't work anymore.

Notice that Nzymes roles are set up but not directly enforced. By limiting enforcement to capabilities, admins can freely create custom roles.

However, Nzymes roles not only document how the different capabilities work together, but they are also a convenient way of managing users' access, using third party plugins, like [WPFront User Role Editor](https://wordpress.org/plugins/wpfront-user-role-editor/) and [many others](https://wordpress.org/plugins/search/roles/). 

--
#### Roles

Roles' display names follow these conventions:

* `User` implies the capability to create static custom fields.
* `Coder` implies the capability to create dynamic custom fields.
* `Trusted` implies the capability to share custom fields with other users.


**Nzymes User**

* Role name: `__nzymes__User`
* Capabilities:
    `__nzymes__inject`
    `__nzymes__use_own_attributes`
    `__nzymes__use_own_custom_fields`
    `__nzymes__create_static_custom_fields`

**Nzymes Privileged User**

* Role name: `__nzymes__PrivilegedUser`
* Capabilities: all those of **Nzymes User** plus
    `__nzymes__use_others_custom_fields`

**Nzymes Trusted User**

* Role name: `__nzymes__TrustedUser`
* Capabilities: all those of **Nzymes Privileged User** plus
    `__nzymes__share_static_custom_fields`

**Nzymes Coder**

* Role name: `__nzymes__Coder`
* Capabilities: all those of **Nzymes Trusted User** plus
    `__nzymes__create_dynamic_custom_fields`

**Nzymes Trusted Coder**

* Role name: `__nzymes__TrustedCoder`
* Capabilities: all those of **Nzymes Coder** plus
    `__nzymes__share_dynamic_custom_fields`

--
#### Capabilities

**inject**

* Capability name: `__nzymes__inject`
    
    *It allows a user to inject enzymes into her posts.*

**use_own_attributes**

* Capability name: `__nzymes__use_own_attributes`
    
    *It allows a user to make her enzymes with her own attributes.*

**use_others_attributes**

* Capability name: `__nzymes__use_others_attributes`
    
    *It allows a user to make her enzymes with other users’ attributes.
    For privacy reasons, only the admin has this capability, i.e. it’s not included into any role. (just a default setting)*

**use_own_custom_fields**

* Capability name: `__nzymes__use_own_custom_fields`
    
    *It allows a user to make her enzymes with her own custom fields.*

**use_others_custom_fields**

* Capability name: `__nzymes__use_others_custom_fields`
    
    *It allows a user to make her enzymes with other users’ custom fields.*

**create_static_custom_fields**

* Capability name: `__nzymes__create_static_custom_fields`
    
    *It allows a user to create enzymes using non-evaluated custom fields.*

**create_dynamic_custom_fields**

* Capability name: `__nzymes__create_dynamic_custom_fields`
    
    *It allows a user to create enzymes using evaluated custom fields.*

**share_static_custom_fields**

* Capability name: `__nzymes__share_static_custom_fields`
    
    *It allows a user to share her enzymes using non-evaluated custom fields.*

**share_dynamic_custom_fields**

* Capability name: `__nzymes__share_dynamic_custom_fields`
    
    *It allows a user to share her enzymes using evaluated custom fields.*


## Part 2: Static Enzymes

### Literal Enzymes

The result of a literal enzyme is always the injected value.

Non negative integers (0, 1, 2...) and strings are the only possible literals.

--
#### Injection of a literal number

**Example**

```
{[ 42 ]}
```

**Result** 

> 42

##### Notes

* Numbers do not need to be quoted. (i.e. wrapped inside a couple of `=` characters)
* Internally, numbers are regular PHP numbers.

--
#### Injection of a literal string

**Example**

```
{[ =Answer to the Ultimate Question of Life, the Universe, and Everything= ]}
```

**Result** 

> Answer to the Ultimate Question of Life, the Universe, and Everything

##### Notes

* Strings are always quoted. (i.e. always wrapped inside a couple of `=` characters)
* You can use a `\=` (backslash equal) to escape an `=` inside a string.
* Internally, strings are regular PHP strings.


### Attribute Enzymes

The result of an attribute enzyme is always the value of the referred attribute.

--
#### Injection of an author attribute enzyme

**Example**

```
{[ /author:display_name ]}
```

**Result** 

> Douglas Adams

##### Notes

* *Origin*: The *author* origin is always referred to by `/author`. A post reference is not present in the example above, then the origin is the author of the current post.
* *Form*: The *attribute* form is always referred to by a starting colon `:`. The attribute in the example above is `display_name`.
* *Behaviour*: The *static* behaviour is always represented by the lack of parentheses `()`.

* [list of author attributes](https://codex.wordpress.org/Function_Reference/get_userdata) (in the *Notes/users* section)

    * ID
    * user_login
    * user_pass
    * user_nicename
    * user_email
    * user_url
    * user_registered
    * display_name

--
#### Injection of a post attribute enzyme

**Example**

```
{[ :post_title ]}
```

**Result** 

> The Hitchhiker's Guide to the Galaxy

##### Notes

* *Origin*: The *post* origin is always referred to by the lack of `/author`. A post reference is not present in the example above, then the origin is the current post.
* *Form*: The *attribute* form is always referred to by a starting colon `:`. The attribute in the example above is `post_title`.
* *Behaviour*: The *static* behaviour is always referred to by the lack of parentheses `()`.

* [list of post attributes](https://codex.wordpress.org/Class_Reference/WP_Post) (in the Member Variables section)

    * ID
    * post_author
    * post_name
    * post_type
    * post_title
    * post_date
    * post_date_gmt
    * post_content
    * post_excerpt
    * post_status
    * comment_status
    * ping_status
    * post_password
    * post_parent
    * post_modified
    * post_modified_gmt
    * comment_count
    * menu_order


### Implicit versus Explicit origins

The above injections have implicit (relative) origins. Nzymes lets you specify explicit (absolute) origins too. If the post of those injections had the `123` ID and the `hitchhikers-guide` slug, then –inside that post– the following injections would mean exactly the same thing:

* `{[ /author:display_name ]}`

    * `{[ 123/author:display_name ]}`
    * `{[@hitchhikers-guide/author:display_name ]}`


* `{[ :post_title ]}`

    * `{[ 123:post_title ]}`
    * `{[ @hitchhikers-guide:post_title ]}`

Explicit origins are very useful from outside the post they reference, in fact they allow you to inject that same information elsewhere. 

For example, while `{[ :post_title ]}` is replaced by *The Hitchhiker's Guide to the Galaxy* in the post whose ID is `123`, `{[ 123:post_title ]}` will always be replaced by that same title everywhere else, for example from the post whose ID is `456` and from some template of WordPress (Nzymes allows you to do that too).


### Custom-field Enzymes

The result of a custom field static enzyme is always the value of the referred custom field.

Custom field transclusion allows you to move content around into your blog, in a fashion very similar to what variables do in programming languages.

By storing a block of text in a custom field instead of having it directly mixed with all the other content of a post, you are effectively associating a name to that block. Using Nzymes injections, you can make that block appear over and over, not only inside the same post but also outside, wherever you might need it.

You could certainly use some short-codes to achieve the same thing, no doubt. However, Nzymes injections are particularly versatile and very clean. Additionally, transclusions are only a piece of the system, even if a fundamental piece.

--
#### Injection of an author custom field static enzyme

**Example**

```
{[ /author.=eye color= ]}
```

**Result**

> Hooloovoo

##### Notes

* *Origin*: The *author* origin is always referred to by `/author`. A post reference is not present in the example above, then the origin is the author of the current post.
* *Form*: The *custom field* form is always referred to by a starting dot `.`. The custom field is `eye color`. Due to the space in the name of the custom field, we need to use an explicit string.
* *Behaviour*: The *static* behaviour is always represented by the lack of parentheses `()`.

* [list of default author custom fields](http://codex.wordpress.org/Function_Reference/get_userdata) (in the *Notes/user_meta* section)

    * first_name
    * last_name
    * nickname
    * description
    * wp_capabilities (array)
    * admin_color (Theme of your admin page. Default is fresh.)
    * closedpostboxes_page
    * primary_blog
    * rich_editing
    * source_domain

--
#### Injection of a post custom field static enzymes

**Example**

{[ @hitchhikers-guide.wikipedia-url ]}

**Result** 

> https://en.wikipedia.org/wiki/The_Hitchhiker%27s_Guide_to_the_Galaxy

##### Notes

* *Origin*: The *post* origin is always referred to by the lack of `/author`. The origin in the example above is the post whose slug is `hitchhikers-guide`.
* *Form*: The *custom field* form is always referred to by a starting dot `.`. The custom field in the example above is `wikipedia-url`. Due to the absence of special characters in the name of the custom field, we can use it directly.
* *Behaviour*: The *static* behaviour is always referred to by the lack of parentheses `()`.


## Part 3: Dynamic Enzymes

What you put in a dynamic enzyme is all regular PHP code, which means powerful and potentially dangerous stuff. You already know it:

> WITH GREAT POWER THERE MUST ALSO COME GREAT RESPONSIBILITY!

If you are concerned about security, Nzymes roles and capabilities allow you to precisely state what users can do.

### Custom-field Enzymes

The result of a custom-field dynamic enzyme is always the value returned after evaluating the value of the referred custom field.

Custom field evaluation allows you to add dynamic content to your blog, in a fashion very similar to what functions do in programming languages. Look at it like this: a post is an object, a custom field name is a method of that object, and the value of that custom field is the body of that method. Thus, without appended parentheses, the locator addresses the text of the code, and with the appended parentheses, the locator represents a call to that code.

--
#### Injection of an author custom field dynamic enzyme

**Example**

```
{{[ @hitchhikers-guide/author.num_posts() ]}
```

**Result**

> 14

##### Notes

* *Origin*: Author of the post with the `hitchhikers-guide` slug.
* *Form*: The *custom field* form in the example above is `num_posts`.
* *Behaviour*: The *dynamic* behaviour is always referred to by appending parentheses `()`.

##### About the code

We want to know how to *count the number of posts by author in WordPress*. The first Google result seems promising: [Function Reference / count user posts](https://codex.wordpress.org/Function_Reference/count_user_posts). The usage example shows: 

```php
$user_post_count = count_user_posts( $userid , $post_type );
```

When executing an enzyme, Nzymes puts many useful values at our disposal. In this case we need `$this->origin_post`, which is the post object with the current injection. The author ID is into its `post_author` property. Here is what the custom field value could be.

```php
$userid = $this->origin_post->post_author;
$post_type = 'post';
$user_post_count = count_user_posts( $userid, $post_type );
return $user_post_count;
```

--
#### Injection of a post custom field dynamic enzyme

**Example**

```
{{[ .num_words() ]}
```

**Result**

> 46000

##### Notes

* *Origin*: Current post.
* *Form*: The *custom field* form in the example above is `num_words`.
* *Behaviour*: The *dynamic* behaviour is always referred to by appending parentheses `()`. 

##### About the code

We want to know how to *count the number of words in a WordPress post*. Again, the first Google result seems promising: [WordPress Word Count Function](http://www.thomashardy.me.uk/wordpress-word-count-function). The usage example shows:

```php
$word_count = str_word_count( strip_tags( $content ) );
```

If we wanted to count the number of words in all the content, we could use `$this->origin_post->post_content`. But that would be the content before starting Nzymes, i.e. all the content including all the injections. 

Instead, if we wanted to count the number of words up until now (before the injection we are currently processing), we can use `$this->new_content`, which is all the filtered content before the current enzyme. 

So, by putting the injection at the very end of our post content we'd get a very good result. Not yet the exact result because the content is still being filtered by other plugins and short-codes, which do alter the content. 

Here is what the custom field value could be.

```php
$content = $this->new_content;
$word_count = str_word_count( strip_tags( $content ) );
return $word_count;
```

Keep reading if you want to know how to get the exact number of words with Nzymes.

### Literal Enzymes

Literals offer a shortcut to their respective custom-field versions. It’d be tedious to create a custom field for literals, but you certainly can.

While literal transclusions get into the content exactly like that, `array` and `assoc` executions get into the content like a standard PHP `array` value. However they only make sense when used together with another dynamic enzyme which consumes them. 

--
#### `array`

**Example**

```
{[ =Jan= | 31 | =Feb= | 28 | =Mar= | 31 | array(6) ]}
```

**Result**

> *array( ‘Jan’, 1, ‘Feb’, 2, ‘Mar’, 3 )*

##### Notes

* *Origin*: Itself and as many previous enzymes as indicated.
* *Form*: literal.
* *Behaviour*: dynamic. Indexed arrays build standard PHP indexed arrays.

--
#### `assoc`

**Example**

```
{[ =Jan= | 31 | =Feb= | 28 | =Mar= | 31 | assoc(3) ]}
```

**Result**

> *array( ‘Jan’ => 1, ‘Feb’ => 2, ‘Mar’ => 3 )*

##### Notes

* *Origin*: Itself and twice as many previous enzymes as indicated.
* *Form*: literal.
* *Behaviour*: dynamic. Associative arrays build standard PHP associative arrays.

--
#### `defer`

WordPress priorities are like points on a time line, so that `1` happens before `2` which happens before `3` and so on.

By default, Nzymes filters at priority `9`, because the default priority of WordPress plugins is `10`. This allows Nzymes to run before most content filters, which allows you to initialize things for others.

Additionally, Nzymes allows you to do the opposite, i.e. fix things for others, by running again at any later priority.

##### How does `{[ ...enzyme-J | defer(X) | enzyme-K... ]}` work?

1. Case when Nzymes is running at an earlier priority `P1` (i.e. such that `P1 < X`):

    1. orderly process all enzymes before `defer(X)`
    1. on `defer(X)`, hook Nzymes at priority `X`; the value of `defer(X)` is `null`
    1. ignore all enzymes after `defer(X)`
    1. reject the whole injection (as if it was never processed at all)
    1. keep processing all the other injections

1. Case when Nzymes is running at a later priority `P2` (i.e. such that `X <= P2`): 

    1. orderly process all enzymes before `defer(X)`
    1. on `defer(X)`, the value of `defer(X)` is `null`
    1. keep processing all enzymes after `defer(X)`
    1. keep processing all the other injections

Notice that the rules above allow you to put `defer(X)` at any position in an injection, but given that enzymes before it are processed and enzymes after it are not (at a lower priority) then the former had a reason to exist only if they produced some side effects you are interested into. Otherwise, make a habit of putting `defer(X)` at the start of an injection, so that it will work the same at any priority.

Rest assured that no hook is added after the first one (with the same filter and priority). This matters when you copy and paste injections containing `defer`: you can ignore duplicates altogether.

**Example**

```
{[ defer(5) | 1 ]}{[ 2 ]}{[ defer(15) | 3 ]}{[ defer(9) | 4 ]}
```

**Result** after priority `9` but before priority `15`

```
12{[ defer(15) | 3 ]}4
```

**Result** after priority `15`

> 1234

Note that `{[ defer(X) ]}` is not only a valid injection, but also a very useful one. In such a case, once processed, the current filter will erase the injection from the content only after having prepared a later execution. That deferred execution will probably process new injections introduced by filters in the middle.


### Direct processing

You can directly process any content with an expression like this:

```php
$result = Nzymes_Plugin::engine()->process($content, $origin, $filter, $priority);
```

or this

```php
$engine = new Nzymes_Engine();
$result = $engine->process($content, $origin, $filter, $priority);
```

depending on whether you want to use the same engine as the one used by the plugin or not. Choose the former when you need to access the context of the plugin when it's processing the current filter, and the latter in any other case, to keep the processing of the `$content` completely separated from the processing of the current filter.


#### Arguments

* `$content` is a string with injections

* `$origin` is the ID of the current post, i.e. the post you want to consider the `$content` as belonging to. It will be used as the implicit / relative origin for all the enzymes into `$content`.

    * use `Nzymes_Engine::GLOBAL_POST` (default) to specify that you want to use whatever is the currently global post
    * use `Nzymes_Engine::NO_POST` to specify that you really don’t want any post

        * if an enzyme into `$content` starts with `/author`, the author would be `Nzymes_Engine::NO_POST_AUTHOR`, i.e. the admin whose ID is `1`

* `$filter` is a WordPress filter; it defaults to `Nzymes_Engine::DIRECT_FILTER`

* `$priority` is a filter priority; it defaults to `Nzymes_Plugin::PRIORITY`

* `process()` works by adding the usual Nzymes filter handler to the given `$filter` at the given `$priority` and immediately calling `apply_filters()` on the given `$content` and `$origin`. This guarantees the same behavior and functionalities both in filter and in direct processing mode.


### Exchanging data

If you need to pass information from one injection to another, inside the same content and priority, you can use `$this->intra`, a property which gets initialized at the start of each new content processing.

If you need to pass information from one injection to another, outside the same content or priority, you can use `$this->extra`, a property which gets initialized at creation time of the Nzymes engine.

**Example**

Let's say that we have a pretty begin of a novel:

> Far out in the uncharted backwaters of the unfashionable end of the western spiral arm of the Galaxy lies a small unregarded yellow sun. Orbiting this at a distance of roughly ninety-two million miles is an utterly insignificant little blue green planet whose ape-descended life forms are so amazingly primitive that they still think digital watches are a pretty neat idea.

We can use Nzymes to show special words in `strong` style, after saving a list of common words, like these [2000 Words of Contemporary Fiction](https://en.wiktionary.org/wiki/Wiktionary:Frequency_lists/Contemporary_fiction), into a custom field of a page.

> Far out in the uncharted backwaters of the unfashionable end of the western spiral arm of the Galaxy lies **{[ @my.start() ]}** a small unregarded yellow sun **{[ @my.end() | @my.special-words(1) ]}**. Orbiting this at a distance of roughly ninety-two million miles is an utterly insignificant little blue green planet whose ape-descended life forms are so amazingly primitive that they still think digital watches are a pretty neat idea.

**Result**

> Far out in the uncharted backwaters of the unfashionable end of the western spiral arm of the Galaxy lies a small **unregarded** yellow sun. Orbiting this at a distance of roughly ninety-two million miles is an utterly insignificant little blue green planet whose ape-descended life forms are so amazingly primitive that they still think digital watches are a pretty neat idea.


##### About the code

Here is how we could code it:

`@my.start`

```php
$this->intra->start_pos = strlen( $this->new_content );
```

`@my.end`

```php
$selected = substr( $this->new_content, $this->intra->start_pos );
$this->new_content = substr( $this->new_content, 0, $this->intra->start_pos );
return $selected;
```

`@my.special-words` 

```php
list( $selected ) = $arguments;

if (! isset($this->extra->ranking)) {
  $engine = new Nzymes_Engine();
  $words = $engine->catalyze('@2000-words-fiction.as-string');
  $this->extra->ranking = array_flip(explode(' ', $words));
}
$ranking = $this->extra->ranking;

$result = preg_replace_callback('/[\w\'-]+/', function ($matches) use ($ranking) {
  $replacement = $matches[0];
  $key = strtolower($replacement);
  if (! isset($ranking[$key])) {
    $replacement = "<strong>$replacement</strong>";
  }
  return $replacement;
}, $selected);

return $result;
```

##### Notes

1. The method used to select text is based on position, and it works perfectly only if `start` and `end` are processed at the same prority, one after the (corresponding) other, like above. See also `defer`.

1. We cache the `ranking` value, because it could be a cause of slowness to prepare it once and again.

1. We use `extra` instead of `intra`, because it allow us to use it also in different filters.

1. `start`, `end`, and `special-words` snippets are designed to be completely portable. We can move them around in that content or copy and paste them, and they continue to work as expected, without having to change anything.


*Example*

> **{[ @my.start() ]}** Far out in the uncharted backwaters of the unfashionable end of the western spiral arm of the Galaxy lies a small unregarded yellow sun. Orbiting this at a distance of roughly ninety-two million miles is an utterly insignificant little blue green planet whose ape-descended life forms are so amazingly primitive that they still think digital watches are a pretty neat idea. **{[ @my.end() | @my.special-words(1) ]}**


*Result*

> Far out in the **uncharted backwaters** of the **unfashionable** end of the **western spiral** arm of the **Galaxy lies** a small **unregarded** yellow sun. **Orbiting** this at a distance of **roughly ninety-two** million **miles** is an **utterly insignificant** little blue green **planet** whose **ape-descended** life **forms** are so **amazingly primitive** that they still think **digital watches** are a pretty **neat** idea.


Notice that some common words appear in strong style above because we use a list of roots without variations:

> Regular plurals are combined with their singular forms (tree, trees; box, boxes). Variations of a verb ending in -ed, -ing or -(e)s are lumped together with their root verb (smile, smiled, smiling, smiles). Adjective forms ending in -er or -est are included with their positive form (sad, sadder, saddest). And words ending in -'s are grouped with the form without the apostrophe (boy, boy's; everything, everything's), except for a few common contractions (it's; that's).


### Debugging

When custom fields are injected by means of dynamic enzymes, their PHP code gets evaluated from inside a method that takes care of the return value, the errors and the output. Before the evaluation, as many arguments as specified by an enzyme are popped from the internal stack and put into the `$arguments` array. After the evaluation, the return value is pushed onto the internal stack; **errors and output are sent to the JavaScript console**.

*Tip*

While developing a dynamic enzyme, frequently check the JavaScript console. There are in fact two kinds of PHP errors: Shutdown errors and non-shutdown errors. The former are easy to detect because they make the PHP interpreter stop at the point they occur and very often the resulting output is awfully broken. The latter instead make the PHP interpreter try to recover. Nzymes takes care of both types and tries to output to the JavaScript console as much information as possible to help you debug your code.

*Tip*

Remember that you can just `echo $something;` from the code in a custom field and see it in the JavaScript console.


**Example**

A shutdown error is captured and shown into the JavaScript console.

![Imgur](http://i.imgur.com/WfbvCVH.png)


### Recommended plugins

Consider the following plugins to help you develop dynamic enzymes.

1. [Debug Bar](https://wordpress.org/plugins/debug-bar/)
1. [Debug Bar Console](https://wordpress.org/plugins/debug-bar-console/)
1. [Query Monitor](https://wordpress.org/plugins/query-monitor/)
1. [User Switching](https://wordpress.org/plugins/user-switching/)
1. [WPFront User Role Editor](https://wordpress.org/plugins/wpfront-user-role-editor/)


## Part 4: Advanced Stuff

### Internal Stack

An Nzymes injection is an expression written following the Reverse Polish notation. A calculator for results of such expressions is easy to implement and quite powerful. All you need is a stack, which is a LIFO data structure: last in, first out. Therefore, Nzymes manages its enzymes by means of an internal stack. The structure of an injection reflects how the internal stack changes during its interpretation.

1. \+ `{[ 3 | .last-comments(1) | .comment-template | .show-comments(2) ]}`

    Before an injection, Nzymes reads each character from the content and writes it to `$this->new_content`.
    * **Stack**: `null`

1. `{[` + `3 | .last-comments(1) | .comment-template | .show-comments(2) ]}`

    Nzymes finds the start of a new injection, suspends writing to `$this->new_content`, and creates an (empty) internal stack.
    * **Stack**: `[]`

1. `{[ 3 ` + `| .last-comments(1) | .comment-template | .show-comments(2) ]}`

    Nzymes finds a literal enzyme and pushes its value (`Value-1 == 3`) onto the internal stack.
    * **Stack**: `[ 3 ]`

1. `{[ 3 | .last-comments(1) ` + `| .comment-template | .show-comments(2) ]}`

    Nzymes finds a dynamic enzyme, pops 1 item (the number 3) and puts it into the `$arguments` array, gets the code from `last-comments` and evaluates it, pushes the result (be it `Value-2`) onto the internal stack.
    * **Stack**: `[ Value-2 ]`

1. `{[ 3 | .last-comments(1) | .comment-template ` + `| .show-comments(2) ]}`

    Nzymes finds a static enzyme, gets the text from `comment-template`, pushes it (be it `Value-3`) onto the internal stack.
    * **Stack**: `[ Value-2 Value-3 ]`

1. `{[ 3 | .last-comments(1) | .comment-template | .show-comments(2) ` + `]}`

    Nzymes finds a dynamic enzyme, pops 2 items (`Value-2` and `Value-3`) and puts them into the `$arguments` array, gets the code from `show-comments` and evaluates it, pushes the result (be it `Value-4`) onto the internal stack.
    * **Stack**: `[ Value-4 ]`

1. `{[ 3 | .last-comments(1) | .comment-template | .show-comments(2) ]}` +

    Nzymes finds the end of the current injection, replaces all the injection with the top item of the stack, and destroys the internal stack. After the injection, Nzymes resumes reading each character from the content and writing it to `$this->new_content`.
    * **Stack**: `null`

Note that it’s not completely by chance that the processing ends with an empty internal stack. In fact RPN calculators consider that a final non-empty stack is an error condition. The practical reason is that you are supposed to push on the stack only something that is going to be used later. If something couldn’t be used before the end of the expression, there should be an error somewhere. Nzymes is forgiving here: a final non-empty internal stack is not an error.


### Difference between `publish`-ed and non-`publish`-ed posts

As long as a `post_status` is different from `publish`, that post exists only for its author: thus Nzymes will always return `null` for enzymes hosted into non-`publish`-ed posts when they are requested from injections into posts of another author. This allows an author to freely experiment with enzymes before `publish`-ing the post that hosts them, i.e. before (possibly) sharing them with others.


### Hooks

--
#### The `__nzymes__post_types` hook

This is a filter that is triggered right before trying to find a post by its slug. In fact, [WordPress' slugs are namespaced by `post_type`](https://core.trac.wordpress.org/ticket/18962), and Nzymes will find custom fields belonging to pages before those belonging to posts, by default.

**Triggering code**

```php
// __nzymes__post_types filters take and return an array of post types to restrict lookup of slugs to.
$post_types = apply_filters( '__nzymes__post_types', $post_types );
```

Notice that the types in this `$post_types` array are at the same time the only types of posts to search and the relevance of the results. Thus, if for example you add a filter handler that returns only one type, then only that type will be searched before returning `null`.

**Example of a listener**

This is how you can force Nzymes to find custom fields belonging to `my_type` posts before `page` and `post` posts.

```php
function my_post_types($post_types) {
    $post_types = array_unshift($post_types, 'my_type');
    return $post_types;
}
add_filter('__nzymes__post_types','my_post_types');
```

With that in place, an injection like `{[ @some-slug.some-custom-field ]}` would make Nzymes look for a post with a `some-slug` slug and a `my_type` type and, if it's not found, then look for one with a `page` type and, if it's not found, then look for one with a `post` type and, if it's not found, then finally give up and return a `null`. (but see also the `__nzymes__missing_post` hook)

--
#### The `__nzymes__missing_post` hook

This is kind of a special filter that is triggered right after failing to find a post by its slug. It's special for two reasons. First it is triggered only if the slug begins with `@@` (a double at-sign) instead of `@` (a single at-sign), and second, it is triggered only if the injection author can create dynamic custom fields.

**Triggering code**

```php
// __nzymes__missing_post filters take a slug and return a WP_Post or null.
$result = apply_filters( '__nzymes__missing_post', $slug );
```

**Example of a listener**

```php
function then_try_this($slug) {
    //...
    return $post;
}
add_filter('__nzymes__missing_post','then_try_this');
```


## Difference between Nzymes and Enzymes

[Enzymes](https://wordpress.org/plugins/enzymes/) (which I first released in 2007) still perfectly works after many years and many WordPress versions and PHP versions in between.

Some time ago I got a new idea for Enzymes: change it to process its sequence of enzymes in a [Reverse Polish Notation](https://en.wikipedia.org/wiki/Reverse_Polish_notation) fashion, thus removing the need for passing arguments in paretheses, a feature which I never liked.

Unfortunaltely, when I designed Enzymes, I decided to store some user's made files (called templates) in a subfolder of the plugin. This was a mistake because WordPress always deletes a plugin (without saving a backup before) when updating it to a new version.

To protect the Enzyme's 40 users (as of 2017) I was forced to change the name of the plugin from Enzymes to Nzymes, thus releasing not an update (from 2.3 to 3) but a brand new plugin (at 1.0), formally unrelated to the original one.


### Use Nzymes without worrying about Enzymes

Nzymes is completely independent from Enzymes and these two plugins can coexist, without ever interfering with each other. In fact, when you install Nzymes, it creates a global option with the current date, which it will later use to process only injections in posts created after that date.

If you want to change that date, for example when you are migrating old Enzymes injections, to make Nzymes process also old posts, just go to your Settings page and change the current page URL from `http.../wp-admin/options-general.php` to `http.../wp-admin/options.php`.

You'll get a long list of options, and near the top of the page you should see the `__nzymes__global_options` entry, whose value would be something like this (but in one line)

```
{
  "installation-time":   "2017-05-10T12:24:36+00:00",
  "process-posts-after": "2017-05-10T12:24:36+00:00",
  "process-also-posts":  [],
  "version":             "1.0.0"
}
```

Please, ignore all but the following settings.

#### process-posts-after

All posts created after this date will be processed by Nzymes.

* Respect the format of the date.

#### process-also-posts

All posts whose IDs belong to this list will be processed by Nzymes, independently from their creation date. 

* Use commas to separate IDs.

*Example* 

```
...
"process-posts-after": "2017-05-10T12:24:36+00:00",
"process-also-posts":  [5,33],
...
```

*Result* 

Nzymes will process all posts created after `2017-05-10` at `12:24:36 UTC` as well as posts with IDs `5` and `33` (even if created before that date).


### How to migrate Enzymes injections to Nzymes

You can transform, if you want, Enzymes injections to Nzymes syntax so that you can force the latter to process posts created before its installation, thus taking advantage of its many improvements.

#### Find posts with injections

Using the [Debug Bar Console](https://wordpress.org/plugins/debug-bar-console/) plugin, you can find all posts where you have injections by running the following query in its SQL tab.

```sql
SELECT post_type, ID, post_name
FROM wp_posts 
WHERE post_status != 'inherit'
AND CONCAT_WS(' - ', post_title, post_content, post_excerpt) LIKE '%{[%'
```

*Example of Result*

|post_type|ID|post_name|
|---|---|---|
|post|5 |first-nzymes-test|
|post|33|another-tes|
|post|50|__trashed-2|
|post|67|test-thumb|


#### Example 1

You have this setup to decorate and HTML-escape some text.

*Injection*

```
{[ .some-text | 1.pre() ]}
```

*Code*

```php
return  '<pre style="padding: 20px;">' . htmlspecialchars( $this->pathway ) . '</pre>';
```

##### Solution

1. Change the injection in the posts to:

    ```
    {[ .some-text | 1.pre(1) ]}
    ```

1. Change the code into the `pre` custom field to:

    ```php
    list( $stuff ) = $arguments; 
    return  '<pre style="padding: 20px;">' . htmlspecialchars( $stuff ) . '</pre>';
    ```


#### Example 2

You have this setup to highlight and HTML-escape some code.

*Injection*

```
{[ .some-code | 1.hilite(=php,ln-1=) ]}
```

*Code*

```php
$arguments = explode( ',', $this->substrate ); 
list( $language, $numbers ) = array_pad( $arguments, 2, null ); 
 
$pre_class = ''; 
$numbers   = trim( $numbers ); 
if ( $numbers ) { 
    $pre_class = ' class="' . $numbers . '"'; 
} 
 
$code_class = ''; 
$language   = trim( $language ); 
if ( $language ) { 
    $code_class = ' class="' . $language . '"'; 
} 
 
$code     = htmlspecialchars( $this->pathway ); 
$template = '<pre%s><code%s>%s</code></pre>'; 
$result   = sprintf( $template, $pre_class, $code_class, $code ); 
 
return $result;
```

##### Solution

1. Change the injection in the posts to:

    ```
    {[ .some-code | =php= | =ln-1= | 1.hilite(3) ]}
    ```

1. Change the code into the `hilite` custom field to:

    ```php
    list( $code, $language, $numbers ) = array_pad( $arguments, 3, null ); 
     
    $pre_class = ''; 
    $numbers   = trim( $numbers ); 
    if ( $numbers ) { 
        $pre_class = ' class="' . $numbers . '"'; 
    } 
     
    $code_class = ''; 
    $language   = trim( $language ); 
    if ( $language ) { 
        $code_class = ' class="' . $language . '"'; 
    } 
     
    $code     = htmlspecialchars( $code ); 
    $template = '<pre%s><code%s>%s</code></pre>'; 
    $result   = sprintf( $template, $pre_class, $code_class, $code ); 
     
    return $result;
    ```


### Nzymes is substantially better than Enzymes

Have a look at the many differences.

|Feature|Enzyme|Nzyme|
|---|---|---|
|Controlled access|Enzymes 2.3 has no roles nor permissions: either you activate the plugin and everything is available to everyone or… well, you deactivate it.|Nzymes has enough roles and permissions to allow you (the admin) to fine tune the right access level for the right users.|
|Engine|Enzymes 2.3 interprets injections from left to right by means of esoteric concepts: the pathway and the content. It’s a bit complicated.|Nzymes allows to effortlessly read an injection and foresee its result. The value returned by an enzyme replaces the enzyme (and all of its arguments, if any) in the injection. The value returned by the last enzyme replaces all the injection. All output is captured and sent to the browser’s console.|
|Reverse Polish Notation|Enzymes 2.3 executions’ arguments, expressed like `locator(=arg-1, arg-2=)` are passed to the code at `locator` by means of an esoteric concept: the substrate. It’s a bit complicated and not very flexible.|Nzymes executions’ arguments, like <code>arg-1 &#124; arg-2 &#124; locator(2)</code> are orderly passed to the code at `locator` by means of the `$arguments` array. (like `[arg-1, arg-2]`)|
|Engine access|Enzymes 2.3 plugin’s engine is a global object. It can execute not only as a filter but also directly, both from outside posts and from inside custom fields. The global `metabolize()` function is used for that.|Nzymes plugin’s engine is a singleton object. It achieves exactly the same by means of the `Nzymes_Plugin::engine()->process()` method.|
|Default post|Enzymes 2.3 always uses the global post by default, if the engine is called directly, without a post object.|Nzymes always uses what is explicitly provided. If the engine needs to work without a post object, `Nzymes_Engine::NO_POST` must be passed. If the engine needs to work with the global post, get_post() can be passed.|
|Templates support|Enzymes 2.3 supports templates, which are files used to output what the injection has prepared. They are a bit complicated and not really useful.|Nzymes has no templates. But you can easily achieve the same result with a dynamic enzyme, if you have at least the Coder role (strengthened security).|
|Author prefix|Enzymes 2.3 uses `~author` to get to the author of a post. A `/` introduces templates.|Nzymes uses `/author` instead. A `/` is much easier to find on some keyboards than a ~.|
|Quoted custom fields|Enzymes 2.3 allows to inject alphanumeric names without wrapping them into `=` quotes.|Nzymes allows to inject unquoted names containing symbols, except `.`, `=`, <code>&#124;</code>, `]`, `}`.|
|Standard attributes|Enzymes 2.3 uses its own attribute names. For example, `{[ :date_gmt ]}` would show the GMT date on which the injection post was created.|Nzymes uses WordPress raw attribute names. For the same date you inject `{[ :post_date_gmt ]}`. This allows a simpler engine and official documentation.|
|Plugin priority|Enzymes 2.3 handles WordPress content filtering at priority `10`. You can change it by editing the `enzymes/enzymes.php` file, and the change affects the whole engine.|Nzymes handles WordPress content filtering at priority `9`. You can change it by editing the `nzymes/src/Enzymes/Plugin.php` file, and the change affects the whole engine.|
|Injection priority|Enzymes 2.3 does not support any other priority than the one for the plugin.|Nzymes allows you to set the priority at which an injection is supposed to be processed (see `defer`).|
|Debugging help|Enzymes 2.3 doesn’t help you in any way while developing execution enzymes. If something fails, you either get a broken post or an invisible result.|Nzymes tries its best at providing you with meaningful information about where the error occurred and why. This information is always sent to the JavaScript console.|
