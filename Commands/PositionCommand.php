<?php

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use League\Csv\Writer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class PositionCommand extends Command
{
    protected static $defaultName = 'position';

    protected $data = [];

    protected function configure()
    {
        $this->setDescription('Get plugin search position')
        ->addArgument('name', InputArgument::REQUIRED, 'Plugin slug');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pluginName = $input->getArgument('name');
        $helper = $this->getHelper('question');

        $question = new Question("Please enter search keywords: \n > ", 'email');

        $keywords = $helper->ask($input, $output, $question);


        $keywords = explode(',', $keywords);
        $keywords = array_map('trim', $keywords);

        $client = $this->getClient();

        foreach ($keywords as $keyword) {
            $response = $client->get('plugins/info/1.2/', [
                RequestOptions::QUERY => [
                    'action' => 'query_plugins',
                    'request' => [
                        'search' => $keyword,
                        'fields' => [
                            'description' => false,
                            'short_description' => false,
                            'ratings' => false,
                            'tags' => false,
                        ],
                        'per_page' => 200
                    ]
                ],
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);

            $data = json_decode($response->getBody()->__toString());

            $this->processResponse($data, $pluginName, $keyword);
        }

        $this->writeOnCSV($pluginName);

        return Command::SUCCESS;
    }

    /**
     * @param $data
     * @param $pluginName
     * @param $keyword
     * @return $this
     */
    protected function processResponse($data, $pluginName, $keyword)
    {
        $position = 0;
        $found = false;

        foreach ($data->plugins as $plugin) {
            if ($plugin->slug == $pluginName) {
                $found = true;
                break;
            }
            $position++;
        }

        if (! $found) {
            $position = 'more then 200';
        }

        $this->data[] = [
            'plugin_name' => $pluginName,
            'keyword' => $keyword,
            'position' => $position,
        ];

        return $this;
    }

    protected function getClient()
    {
        return new Client([
            'base_uri' => 'https://api.wordpress.org/'
        ]);
    }

    protected function writeOnCSV($pluginName)
    {
        $filePath = dirname(__DIR__) . "/reports/report-{$pluginName}-" . date('Y-m-d') .'.csv';

        if (! file_exists($filePath)) {
            touch($filePath);
        }

        $csv = Writer::createFromPath($filePath);

        $csv->insertOne([
            'plugin_name',
            'keyword',
            'position'
        ]);

        $csv->insertAll($this->data);

        $csv->output();
    }
}
