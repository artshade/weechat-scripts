<?php

/**
 * @TODO Constant script metadata
 *
 * @TODO A proper class with normal declarations. Currently, it seems that "weechat-php" plugin of Weechat v4.0.4
 *     as of 2023-08, does not support declaration invalidation and leaves leftovers like defined globals, variables,
 *     or classes. In the result, a class declaration with a similar name causes relatively unexpected behavior.
 */

// Variables
// ----------------------------------------------------------------

$_script = [
    'author'      => 'Artfaith',
    'description' => 'Custom PHP (v8.2) test script for Weechat (v4.1.0).',
    'license'     => 'MIT',
    'name'        => 'custom_test',
    'version'     => '0.1-2023-08',
];

$_config = [
    'buffers' => [
        'description' => 'Comma-separated buffer list to process. If empty - process all buffers.',
        'type'        => 'string',
        'value'       => '',
    ],

    'message_count' => [
        'description' => 'Test message count to send.',
        'type'        => 'integer',
        'value'       => 2,
    ],
];

$_globals = [
    'settingsRootSectionPath' => "plugins.var.php.{$_script['name']}",
];

// Functions (Configuration)
// ----------------------------------------------------------------

/**
 * @param $key
 *
 * @return mixed
 * @throws Exception
 */
$_getConfigItem = function ($key) use (&$_config) {
    if (!array_key_exists($key, $_config)) {
        throw new Exception("Unknown configuration item: '{$key}'", 1);
    }

    return $_config[$key];
};

/**
 * @param $key
 *
 * @return string
 * @throws Exception
 */
$_getConfigValueType = function ($key) use ($_getConfigItem) {
    $configItem = $_getConfigItem($key);

    if (!array_key_exists('type', $configItem)) {
        throw new Exception("Malformed configuration value: '$key'", 1);
    }

    $configValueType = $configItem['type'] ?? 'string';

    if (!in_array($configValueType, ['boolean', 'integer', 'string'], true)) {
        throw new Exception("Unsupported configuration value type: '$configValueType'", 1);
    }

    return $configValueType;
};

/**
 * @param string $key - Configuration item key
 *
 * @return bool|int|string|null
 *
 * @throws Exception
 */
$_getConfigValue = function (string $key) use ($_getConfigItem): bool|int|string|null {
    $configItem = $_getConfigItem($key);

    if (!array_key_exists('value', $configItem)) {
        throw new Exception("Malformed configuration value: '$key'", 1);
    }

    return $configItem['value'];
};

/**
 * Set script configuration value. The value is cast into expected configuration value type automatically.
 *
 * @param bool|int|string $value - Configuration item value
 * @param string $key - Configuration item key
 *
 * @return bool - Boolean "true" if set
 */
$_setConfigValue = function (bool|int|string $value, string $key) use (&$_config, $_getConfigValueType): bool {
    settype($value, $_getConfigValueType($key));
    $previousValue = $_config[$key]['value'];

    if ($previousValue === $value) {
        return false;
    }

    $_config[$key]['value'] = $value;
    weechat_print('', "[ ! ] Settings item has changed, '$key': '$previousValue' -> '{$_config[$key]['value']}'");

    return true;
};

/**
 * @param $value - [Reference] Configuration item value reference to set
 * @param string $key - Configuration item key
 * @param string|null $default - Default value to set in case of no configuration item found
 *
 * @return bool - Boolean "true" if found a requested configuration item or
 *     set to a specified default value, else - "false"
 */
$_getSettingsConfigValue = function (&$value, string $key, ?string $default = null): bool {
    if (weechat_config_is_set_plugin($key) !== 1) {
        if (!isset($default)) {
            return false;
        }

        $value = $default;
    } else {
        $value = weechat_config_get_plugin($key);
    }

    return true;
};

/**
 * @param $value
 * @param $key
 *
 * @return int
 */
$_setSettingsConfigValue = function ($value, $key): int {
    return weechat_config_set_plugin($key, $value);
};

$_configFunctions = [
    'getConfigItem'          => $_getConfigItem,
    'getConfigValueType'     => $_getConfigValueType,
    'getConfigValue'         => $_getConfigValue,
    'setConfigValue'         => $_setConfigValue,
    'getSettingsConfigValue' => $_getSettingsConfigValue,
    'setSettingsConfigValue' => $_setSettingsConfigValue,
];

// Functions
// ----------------------------------------------------------------

/**
 * @param $name
 *
 * @return bool
 */
$_isBufferAllowed = function ($buffer) use ($_configFunctions): bool {
    $bufferName     = weechat_buffer_get_string($buffer, 'name');
    $allowedBuffers = explode(',', (string) $_configFunctions['getConfigValue']('buffers'));
    $allowedBuffers = array_filter($allowedBuffers, fn ($bufferName) => !empty($bufferName));

    if (
        empty($allowedBuffers[0])
        || in_array($bufferName, $allowedBuffers)
    ) {
        return true;
    }

    return false;
};

// Handlers
// ----------------------------------------------------------------
// Command Handlers
// --------------------------------

/**
 * Write a test message into the corresponding buffer.
 *
 * @param string $data
 * @param string $bufferName
 * @param string $args
 *
 * @return int
 */
$_onCommandTestMessage = function (string $data, string $buffer, string $args) use ($_isBufferAllowed, $_configFunctions): int {
    if (!$_isBufferAllowed($buffer)) {
        $bufferFullName = weechat_buffer_get_string($buffer, 'full_name');

        // Print a test message in the "core" ("weechat") buffer
        weechat_print('', "Issued command 'testmessage' in a disallowed buffer (\"$bufferFullName\"), args: $args");

        return WEECHAT_RC_OK;
    }

    // Print a message(-s) in the issued command buffer

    $messageCount = (int) $_configFunctions['getConfigValue']('message_count');

    if ($messageCount <= 1) {
        weechat_print($buffer, "Hurray! Command 'testmessage', args: $args");

        return WEECHAT_RC_OK;
    }

    for ($i = 1; $i <= $messageCount; ++$i) {
        weechat_print($buffer, "Hurray #$i! Command 'testmessage', args: $args");
    }

    return WEECHAT_RC_OK;
};

// Hook Handlers
// --------------------------------

/**
 * @param $data
 * @param $fullKey
 * @param $value
 *
 * @return int
 */
$_onConfigChange = function ($data, $fullKey, $value) use ($_globals, $_configFunctions): int {
    $rootSectionPathRegex = "/^{$_globals['settingsRootSectionPath']}\\./";

    if (!preg_match($rootSectionPathRegex, $fullKey)) {
        return WEECHAT_RC_OK;
    }

    $key = preg_replace($rootSectionPathRegex, '', $fullKey);
    $_configFunctions['setConfigValue']($value, $key);

    return WEECHAT_RC_OK;
};

// Functions (complex)
// ----------------------------------------------------------------

/**
 * @throws Exception
 */
$_setConfig = function () use (
    $_script,
    $_config,
    $_configFunctions,
    $_onConfigChange
): void {
    weechat_print('', '[ * ] Setting configuration');

    // For each config item

    foreach ($_config as $configKey => $configItem) {
        $configDefaultValue     = $configItem['value'];
        $configValueDescription = $configItem['description'];

        weechat_config_set_desc_plugin($configKey, $configValueDescription);

        // Try getting a config item from Weechat settings

        if ($_configFunctions['getSettingsConfigValue']($value, $configKey)) {
            if ($_configFunctions['setConfigValue']($value, $configKey)) {
                weechat_print('', '[ + ] Set found config value: ' . "'$configKey'");
            }

            continue;
        }

        // Set a default config item value if not found in the settings

        $_configFunctions['setSettingsConfigValue']($configDefaultValue, $configKey);
        weechat_print('', '[   ] Set default config value: ' . "'$configKey'");
    }

    // weechat_config_set_desc_plugin

    if (weechat_hook_config("plugins.var.php.{$_script['name']}.*", $_onConfigChange, '') === null) {
        throw new Exception('Failed hooking configuration', 1);
    }

    weechat_print('', '[ + ] Set configuration');
};

$_loadScript = function () use ($_script, $_onCommandTestMessage): void {
    weechat_print('', "[ * ] Loading script '{$_script['name']}'");

    // Command: 'testmessage'
    weechat_hook_command(
    // Command name
        'testmessage',
        // Command description
        'This a test command',
        // Command options
        'option1 [-a|-b] || option2',
        // Option descriptions
        'option1: Test Option #1\n' .
        'option2: Test Option #2',
        // Option completion
        'option1 || option2',
        // Command callback
        $_onCommandTestMessage,
        // Callback data
        ''
    );

    weechat_print('', "[ + ] Loaded script '{$_script['name']}'");
};

$_unloadScript = function () use ($_script): void {
    weechat_print('', "[ * ] Unloading script '{$_script['name']}'");
    weechat_unhook_all($_script['name']);
    weechat_print('', "[ + ] Unloaded script '{$_script['name']}'");
};

// Main
// --------------------------------

$_main = function () use ($_setConfig, $_loadScript): void {
    $_setConfig();
    $_loadScript();
};

// Main
// ----------------------------------------------------------------

weechat_print('', '----------------------------------------------------------------');
weechat_print('', 'PHP version: \'' . phpversion() . '\'');

try {
    if (
        weechat_register(
            $_script['name'],
            $_script['author'],
            $_script['version'],
            $_script['license'],
            $_script['description'],
            $_unloadScript, // Shutdown function
            '' // Charset
        ) !== 1
    ) {
        throw new Exception('Could not register the script.', 1);
    }
} catch (Throwable $exception) {
    weechat_print('', '[ - ] Failed registering script: ' . $exception->getMessage());

    exit($exception->getCode());
}

$_main();
