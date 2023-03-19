# mailmanAPI

This is a fork from [splattner/mailmanAPI](https://github.com/splattner/mailmanAPI) trying to enable the API-script for mailman 3.x.

As Mailman 3.x seems not to offer a proper API, this Mailman API provides some basic functionality to work with Mailman.
Be aware, the library only wrappes around the HTML Forms of the Mailman API Site. It parses the HTTP Responses & HTML Pages, for Authentication Cookies, CSRF TOKEN and then posts to the FORM action url.

Testes with Postorius Version 1.3.8, no guarantee to work with other versions.

## Features

- Get all Maillists
- Get all Members of a Maillist
- Add Members to a Maillist
- Remove Members from a Maillist

## Requirements

- Socket enabled or curl extension installed
- PHP 5.3+

## Installation

``` { .shell }
composer require eudo1111/mailmanapi:^1.0
```

## Usage

You need the URL for your Mailman Mailist e.g. https://{{domain}}/mailman3/lists/{{maillistName}}, your Email and your Administration Password for the Maillist.

- [mailmanAPI](#mailmanapi)
  - [Features](#features)
  - [Requirements](#requirements)
  - [Installation](#installation)
  - [Usage](#usage)
    - [Get All Maillists](#get-all-maillists)
    - [Get All Members](#get-all-members)
    - [Add Members](#add-members)
    - [Remove Members](#remove-members)
    - [Change Member](#change-member)

### Get All Maillists

``` { .php }
$mailman = new MailmanAPI($mailManBaseURL,$adminPW,$adminEmail);
$allMembers = $mailman->getMaillists();
```

### Get All Members

``` { .php }
$mailman = new MailmanAPI($mailManBaseURL,$adminPW,$adminEmail);
$allMembers = $mailman->getMemberlist();
```

### Add Members

``` { .php }
$mailman = new MailmanAPI($mailManBaseURL,$adminPW,$adminEmail);
$mailman->addMembers(["member1@domain.com","member2@domain.com"]);
```

### Remove Members

``` { .php }
$mailman = new MailmanAPI($mailManBaseURL,$adminPW,$adminEmail);
$mailman->removeMembers(["member1@domain.com","member2@domain.com"]);
```

### Change Member

``` { .php }
$mailman = new MailmanAPI($mailManBaseURL,$adminPW,$adminEmail);
$mailman->changeMember("memberold@domain.com","membernew@domain.com");
```
