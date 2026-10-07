<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
#[Group('api')]
class ReadOnlySchemaTest extends WebTestCase
{
    /** @return list<array<string, mixed>> */
    private function fetchSupportedClasses(): array
    {
        $client = static::createClient();
        $client->request('GET', '/docs.jsonld', [], [], ['HTTP_ACCEPT' => 'application/ld+json']);

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var array{supportedClass?: list<array<string, mixed>>} $data */
        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['supportedClass'] ?? [];
    }

    /**
     * @param list<array<string, mixed>> $classes
     *
     * @return array<string, mixed>
     */
    private function findClass(array $classes, string $title): array
    {
        foreach ($classes as $class) {
            if (($class['title'] ?? null) === $title) {
                return $class;
            }
        }

        $this->fail(sprintf('Class "%s" not found in API documentation.', $title));
    }

    /**
     * @param array<string, mixed> $class
     */
    private function assertPropertyNotWritable(array $class, string $property): void
    {
        foreach ($class['supportedProperty'] ?? [] as $entry) {
            if (!is_array($entry) || ($entry['title'] ?? null) !== $property) {
                continue;
            }

            $this->assertFalse(
                $entry['writeable'] ?? $entry['writable'] ?? true,
                sprintf('Property "%s" of "%s" must not be writable.', $property, $class['title'] ?? '?'),
            );

            return;
        }

        $this->fail(sprintf('Property "%s" not found in "%s".', $property, $class['title'] ?? '?'));
    }

    #[Test]
    public function user_profile_exposes_roles_and_verification_as_read_only(): void
    {
        $profile = $this->findClass($this->fetchSupportedClasses(), 'UserProfile');

        $this->assertPropertyNotWritable($profile, 'roles');
        $this->assertPropertyNotWritable($profile, 'isVerified');
        $this->assertPropertyNotWritable($profile, 'createdAt');
    }

    #[Test]
    public function admin_user_exposes_roles_and_verification_as_read_only(): void
    {
        $admin = $this->findClass($this->fetchSupportedClasses(), 'AdminUser');

        $this->assertPropertyNotWritable($admin, 'roles');
        $this->assertPropertyNotWritable($admin, 'isVerified');
    }
}
