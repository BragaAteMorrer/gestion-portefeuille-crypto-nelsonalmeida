<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthControllerTest extends WebTestCase
{
    public function testHealthEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health');

        $status = $client->getResponse()->getStatusCode();
        $this->assertContains($status, [200, 503], 'Health must respond 200 or 503');

        $data = json_decode($client->getResponse()->getContent(), true) ?? [];
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('db', $data);
    }
}
