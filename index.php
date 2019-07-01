<?php
// Only valid if PHP7 or greater
declare(strict_types = 1);

namespace Avonture;

require_once 'vendor/autoload.php';

use \Classes\Csv2Md;
use \Classes\DbDocument;
use \Classes\Helper;

// Source of this script on github
define('REPO', 'https://github.com/cavo789/db_documentor');

// Name of the settings file
define('SETTINGS', 'settings.json');

// Enable or not the debug mode
define('DEBUG', true);

/**
 * Read the settings.json file.
 * If something goes wrong with the file, exit the script and show an error message.
 *
 * @return array
 */
function readSettings(): array
{
    $arr = [];

    if (file_exists(SETTINGS)) {
        $json = file_get_contents(SETTINGS);
        if ('' == trim($json)) {
            die('Sorry, the ' . SETTINGS . ' file is empty, please read the documentation at ' . REPO);
        }

        $arr = json_decode($json, true);
    } else {
        // die('Sorry, the ' . SETTINGS . ' file is missing, please read the documentation at ' . REPO);
    }

    return $arr;
}

/**
 * A database name has been selected; retrieve informations and generate doc.
 *
 * @param string $dbName
 *
 * @return array
 */
function doIt(string $dbName): array
{
    // Just in case
    if ('' == $dbName) {
        return
            [
                'status'  => '0',
                'message' => 'Error, no database name provided; something ' .
                    'goes wrong with the ajax call',
            ];
    }

    $dbName = base64_decode($dbName);

    $arr = readSettings();

    foreach ($arr['databases'] as $db) {
        if ($db['name'] == $dbName) {
            break;
        }
    }

    // In case of: the search dbname isn't found in the settings file. Should not occurs
    // except if the file has been updated after that the form has been displayed.
    if ($db['name'] !== $dbName) {
        return
            [
                'status'  => '0',
                'message' => 'Error, no database called ' . $dbName . ' has been ' .
                    'retrieved in your ' . SETTINGS . 'file',
            ];
    }

    // Ok, we've retrieved our database from the settings file
    $arrReturn = [];

    // 1 for success
    $arrReturn['status'] = 1;

    // Instantiate and initialize our class
    $csvParser = new Csv2Md();
    $dbDoc     = new DbDocument($db, $csvParser);

    $dbDoc->setDebug(DEBUG);

    // Get the configuration coming from the setting file
    $dbDoc->setTimeZone($arr['config']['timezone'] ?? 'Europe/Brussels');
    $dbDoc->setTimeFormat($arr['config']['timeformat'] ?? 'd/m/Y H:i:s');
    $dbDoc->setMaxRows(intval($arr['config']['maxrows'] ?? 5));
    $dbDoc->setCreateCSV(boolval($arr['config']['create_csv'] ?? true));
    $dbDoc->setCreateMD(boolval($arr['config']['create_md'] ?? true));
    $dbDoc->setCreateCustomMD(boolval($db['output']['add_custom_files'] ?? true));
    $dbDoc->setCreateMarknotes(boolval($arr['config']['create_marknotes'] ?? false));
    $dbDoc->setCreateGitLabWiki(boolval($arr['config']['create_gitlab_wiki'] ?? true));
    $dbDoc->setCreateSQL(boolval($arr['config']['create_sql'] ?? true));
    $dbDoc->setCSVSeparator($arr['config']['csv_separator'] ?? ',');

    // Get templates to use
    if (boolval($arr['config']['create_gitlab_wiki'] ?? false)) {
        $dbDoc->setTemplates($arr['gitlab_wiki']['templates'] ?? []);
    } elseif (boolval($arr['config']['create_marknotes'] ?? false)) {
        $dbDoc->setTemplates($arr['marknotes']['templates'] ?? []);
    }

    // For easiness, return a HTML string
    if (boolval($arr['config']['get_credentials'] ?? true)) {
        $arrReturn['credentials'] = $dbDoc->getHTMLCredentials();
    }

    if (!$dbDoc->init()) {
        $arrReturn['status']  = 0;
        $arrReturn['message'] = 'Fatal error with the database connection, ' .
            'invalid credentials, please review your ' . SETTINGS . ' file';
    }

    // For easiness, return a HTML string
    $arrReturn['tables'] = $dbDoc->getListOfTables();

    if (boolval($arr['config']['get_detail'] ?? true)) {
        $arrReturn['detail'] = $dbDoc->getTablesDetail();
    }

    if (boolval($arr['config']['createCsv'] ?? true) ||
        boolval($arr['config']['createMd'] ?? true)
    ) {
        $arrReturn['conclusion'] = 'Files have been created in folder ' . $db['output']['folder'] . '.';

        $url = trim(strval($db['output']['url']) ?? '');
        if ('' !== $url) {
            $link = '<a href="' . $db['output']['url'] . '" target="_blank">' .
                'See documentation online</a>';
            $arrReturn['conclusion'] .= ' ' . $link;
        }
    } else {
        $arrReturn['conclusion'] = 'The database has been successfully processed';
    }

    // No more needed, release the object
    unset($dbDoc);

    return $arrReturn;
}

Helper::initDebug(DEBUG);

// Get the data sent by Ajax.
$data   = json_decode(file_get_contents('php://input'), true);
$task   = trim(filter_var(($data['task'] ?? ''), FILTER_SANITIZE_STRING));
$dbName = trim(filter_var(($data['dbName'] ?? ''), FILTER_SANITIZE_STRING));

if ('doIt' === $task) {
    $result = doIt($dbName);

    // Do the job. doIt() will return an array
    die(json_encode($result));
} else {
    // Read the settings.json file and initialize the list of databases
    $arr = readSettings();

    // Get the list of databases
    $sDBNames = '';

    // Sort databases
    if (isset($arr['databases'])) {
        sort($arr['databases']);

        // $sDBNames will be used for our <select>...</select> for allowing the user to
        // select a database
        foreach ($arr['databases'] as $db) {
            $sDBNames .= '<option value="' . $db['name'] . '">' . $db['name'] . '</option>';
        }
    }
}

// Get the GitHub corner
$github = '';
if (is_file($cat = __DIR__ . DIRECTORY_SEPARATOR . 'octocat.tmpl')) {
    $github = str_replace('%REPO%', REPO, file_get_contents($cat));
}

?>

<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8"/>
        <meta name="author" content="Christophe Avonture" />
        <meta name="robots" content="noindex, nofollow" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta http-equiv="content-type" content="text/html; charset=UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=9; IE=8;" />
        <title>Database documentation tool</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bulma/0.7.4/css/bulma.min.css" rel="stylesheet" media="screen" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.15.0/themes/prism.min.css" rel="stylesheet" media="screen" />
        <link href="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.25.3/css/theme.ice.min.css" rel="stylesheet" media="screen" />
   </head>

   <body>

        <?php echo $github; ?>

        <section class="section">
            <div class="container">
                <h1 class="title is-3">Database documentor</h1>
                <p class="subtitle">Document each table of your database; create .csv and multiple .md files so you can easily retrieve these information's for your favorites documentation tool.</p>

                <small class="content has-text-info">
                    The configuration is coming from the
                    <?php echo __DIR__ . DIRECTORY_SEPARATOR . SETTINGS; ?> file;
                    if the file isn't there, please copy `settings.json.dist` and name the new file
                    <?php echo __DIR__ . DIRECTORY_SEPARATOR . SETTINGS; ?> needed please edit it and adapt the program to your needs. Read documentation
                    on <a href="<?php echo REPO; ?>">GitHub</a> to learn how to do.
                </small>

                <hr/>

                <div id="app">

                    <div class="field is-horizontal">
                        <div class="field-label is-normal">
                            <label class="label" for="select_dbname">Please select your database:</label>
                        </div>
                        <div class="field-body">
                            <div class="field">
                                <p class="control">
                                    <span class="select">
                                        <select  @change="selectDbName" class="select" v-model="name" id="select_dbname" ><?php echo $sDBNames; ?></select>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <h2 class="title is-4" v-if="name">{{ name }}</h2>

                    <errors v-if="errors.length" :errors="errors"></errors>

                    <loading v-if="name" :loading="loading"></loading>

                    <credentials v-if="credentials" :html="credentials"></credentials>

                    <tables v-if="tables" :html="tables"></tables>

                    <detail v-if="detail" :html="detail"></detail>

                    <conclusion :html="conclusion"></conclusion>

                </div>
            </div>
        </section>

        <script src="https://unpkg.com/vue"></script>
        <script src="https://unpkg.com/axios/dist/axios.min.js"></script>

        <!-- jQuery need for tablesorter plugin; used in the "List of tables" part -->
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.tablesorter/2.25.3/js/jquery.tablesorter.combined.min.js"></script>

        <!-- Prism - Highlight SQL statements -->
        <script type="text/javascript" data-manual src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.15.0/prism.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.15.0/components/prism-sql.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.15.0/components/prism-plsql.min.js"></script>

        <script>
            Vue.component("loading", {
                template:
                    `<div v-if="loading" class="content has-text-info">
                        <b>Loading... Please wait... Documenting a database is a slow process</b>
                    </div>`,
                props: {
                    loading: {
                        type: Boolean
                    }
                }
            });

            Vue.component("errors", {
                template:
                    `<div class="content has-text-danger">
                        <b>Please correct the following error(s):</b>
                        <ul>
                            <li v-for="error in errors">{{ error }}</li>
                        </ul>
                    </div>`,
                props: {
                    errors: {
                        type: Array
                    }
                }
            });

            Vue.component("credentials", {
                template:
                    `<div class="content">
                        <details>
                            <summary>Connection information</summary>
                            <div v-html="html" />
                        </details>
                    </div>`,
                props: {
                    html: {
                        type: String
                    }
                }
            });

            Vue.component("tables", {
                template:
                    `<div class="content">
                        <details>
                            <summary>Summary of tables</summary>
                            <div v-html="html" />
                        </details>
                    </div>`,
                props: {
                    html: {
                        type: String
                    }
                },
                mounted() {
                    // Make the table sortable thanks to the tableSorter plugin
                    $("#tbl").tablesorter({
                        theme: "ice",
                        widthFixed: false,
                        sortMultiSortKey: "shiftKey",
                        sortResetKey: "ctrlKey",
                        headers: {
                        0: {sorter: "text"}, // Table name
                        1: {sorter: "digit"} // Number of records
                        },
                        ignoreCase: true,
                        headerTemplate: "{content} {icon}",
                        widgets: ["uitheme", "filter"],
                        initWidgets: true,
                        widgetOptions: {
                        uitheme: "ice"
                        },
                        sortList: [[0]]  // Sort by default on the table name
                    });
                }
            });

            Vue.component("detail", {
                template:
                    `<div class="content">
                        <details>
                            <summary>List of tables</summary>
                            <div v-html="html" />
                        </details>
                    </div>`,
                props: {
                    html: {
                        type: String
                    }
                }
            });

            Vue.component("conclusion", {
                template:
                    `<div class="content has-text-success"><div v-html="html" /></div>`,
                props: {
                    html: {
                        type: String
                    }
                }
            });

            var app = new Vue({
                el: '#app',
                data: {
                    conclusion: '',
                    credentials: '',
                    detail: '',
                    name: '',
                    errors: [],
                    status: 0,
                    tables: '',
                    loading: false
                },
                methods: {
                    selectDbName() {
                        this.errors = [];
                        this.conclusion = '';
                        this.credentials = '';
                        this.detail = '';
                        this.tables = '';
                        this.loading = true;

                        var $data = {
                            task: 'doIt',
                            dbName: window.btoa(this.name)
                        }

                        axios.post('<?php echo basename(__FILE__); ?>', $data)
                        .then(response => {
                            if (response.data.status==0) {
                                // status = 0 means errors
                                this.errors.push(response.data.message);
                            }

                            this.loading = false;

                            this.credentials = response.data.credentials;
                            this.tables = response.data.tables;
                            this.detail = response.data.detail;
                            this.conclusion = response.data.conclusion;
                        })
                        .catch(function (error) {console.log(error);})
                        .then(function() {
                            if (typeof Prism === 'object') {
                                // Use prism.js and highlight source code
                                Prism.highlightAll();
                            }
                        });
                    }
                }
            });
        </script>

   </body>
</html>
