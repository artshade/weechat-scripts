# weechat-scripts
Weechat Scripts

# Script 'custom_test.php'

1. Copy the file to the PHP script directory (e.g. `~/.local/share/weechat/php/`);
2. Ensure [weechat-php](https://github.com/weechat/weechat/tree/master/src/plugins/php) plugin is installed or compiled for required PHP version;
3. Issue Weechat commands to load the script `/script load custom_test.php`;
4. Check script loading messages in the "core" Weechat buffer;
5. Check corresponding Weechat settings if required:

   1. `plugins.var.php.custom_test.buffers`;
   2. `plugins.var.php.custom_test.message_count`;

6. Issue command `/testcommand` in a few buffers if required.
