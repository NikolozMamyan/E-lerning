<?php

declare(strict_types=1);

namespace App\Service\Scorm;

use App\Service\Scorm\Exception\ScormGenerationException;

final class ScormManifestGenerator
{
    private const IMS_NAMESPACE = 'http://www.imsproject.org/xsd/imscp_rootv1p1p2';
    private const ADLCP_NAMESPACE = 'http://www.adlnet.org/xsd/adlcp_rootv1p2';
    private const XSI_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

    public function generate(string $courseIdentifier, string $courseTitle, array $resourcePaths): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $document->preserveWhiteSpace = false;

        $manifest = $document->createElementNS(self::IMS_NAMESPACE, 'manifest');
        $manifest->setAttribute('identifier', 'MANIFEST-'.$this->normalizeIdentifier($courseIdentifier));
        $manifest->setAttribute('version', '1.0');
        $manifest->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:adlcp', self::ADLCP_NAMESPACE);
        $manifest->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XSI_NAMESPACE);
        $manifest->setAttributeNS(
            self::XSI_NAMESPACE,
            'xsi:schemaLocation',
            self::IMS_NAMESPACE.' imscp_rootv1p1p2.xsd '.self::ADLCP_NAMESPACE.' adlcp_rootv1p2.xsd',
        );
        $document->appendChild($manifest);

        $metadata = $document->createElementNS(self::IMS_NAMESPACE, 'metadata');
        $metadata->appendChild($document->createElementNS(self::IMS_NAMESPACE, 'schema', 'ADL SCORM'));
        $metadata->appendChild($document->createElementNS(self::IMS_NAMESPACE, 'schemaversion', '1.2'));
        $manifest->appendChild($metadata);

        $organizations = $document->createElementNS(self::IMS_NAMESPACE, 'organizations');
        $organizations->setAttribute('default', 'ORG-1');
        $organization = $document->createElementNS(self::IMS_NAMESPACE, 'organization');
        $organization->setAttribute('identifier', 'ORG-1');
        $organization->appendChild($this->elementWithText($document, 'title', $courseTitle));
        $item = $document->createElementNS(self::IMS_NAMESPACE, 'item');
        $item->setAttribute('identifier', 'ITEM-1');
        $item->setAttribute('identifierref', 'RESOURCE-1');
        $item->appendChild($this->elementWithText($document, 'title', $courseTitle));
        $organization->appendChild($item);
        $organizations->appendChild($organization);
        $manifest->appendChild($organizations);

        $resources = $document->createElementNS(self::IMS_NAMESPACE, 'resources');
        $resource = $document->createElementNS(self::IMS_NAMESPACE, 'resource');
        $resource->setAttribute('identifier', 'RESOURCE-1');
        $resource->setAttribute('type', 'webcontent');
        $resource->setAttributeNS(self::ADLCP_NAMESPACE, 'adlcp:scormtype', 'sco');
        $resource->setAttribute('href', 'index.html');

        foreach ($resourcePaths as $path) {
            $file = $document->createElementNS(self::IMS_NAMESPACE, 'file');
            $file->setAttribute('href', $path);
            $resource->appendChild($file);
        }

        $resources->appendChild($resource);
        $manifest->appendChild($resources);

        $xml = $document->saveXML();
        if ($xml === false) {
            throw new ScormGenerationException('Unable to generate the SCORM manifest.');
        }

        return $xml;
    }

    private function elementWithText(\DOMDocument $document, string $name, string $value): \DOMElement
    {
        $element = $document->createElementNS(self::IMS_NAMESPACE, $name);
        $element->appendChild($document->createTextNode($value));

        return $element;
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $normalized = preg_replace('/[^A-Za-z0-9_.-]+/', '-', trim($identifier)) ?? '';
        $normalized = trim($normalized, '-');

        if ($normalized === '') {
            $normalized = 'course';
        }

        if (!preg_match('/^[A-Za-z_]/', $normalized)) {
            $normalized = 'course-'.$normalized;
        }

        return $normalized;
    }
}
