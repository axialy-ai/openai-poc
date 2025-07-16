<?php
/**
 * Creates a valid DOCX (Office Open XML) file from simple text content,
 * returning the file bytes as a string. Requires ZipArchive extension.
 */
function createDocxFromText($plainText) {
    // Minimal Word/document.xml with a single paragraph containing $plainText
    // We escape special XML characters in $plainText with htmlspecialchars()
    $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r>
        <w:t>'. htmlspecialchars($plainText) .'</w:t>
      </w:r>
    </w:p>
  </w:body>
</w:document>';

    // Create a temporary file for zipping
    $zip = new ZipArchive();
    $tmpFile = tempnam(sys_get_temp_dir(), 'docx_');
    $zip->open($tmpFile, ZipArchive::CREATE);

    // [Content_Types].xml
    $zip->addFromString('[Content_Types].xml', getContentTypesXml());

    // _rels/.rels
    $zip->addFromString('_rels/.rels', getRelationshipsXml());

    // word/document.xml (our main doc content)
    $zip->addFromString('word/document.xml', $documentXml);

    // word/_rels/document.xml.rels
    $zip->addFromString('word/_rels/document.xml.rels', getDocumentRelsXml());

    // docProps/core.xml
    $zip->addFromString('docProps/core.xml', getDocPropsCoreXml());

    // docProps/app.xml
    $zip->addFromString('docProps/app.xml', getDocPropsAppXml());

    // Done building the docx
    $zip->close();

    // Read the file bytes back, then delete temp
    $docxData = file_get_contents($tmpFile);
    unlink($tmpFile);

    return $docxData;
}

// Minimal XML for each of these required files:
function getContentTypesXml() {
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>
XML;
}

function getRelationshipsXml() {
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" 
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" 
    Target="word/document.xml"/>
  <Relationship Id="rId2"
    Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties"
    Target="docProps/core.xml"/>
  <Relationship Id="rId3"
    Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties"
    Target="docProps/app.xml"/>
</Relationships>
XML;
}

function getDocumentRelsXml() {
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
</Relationships>
XML;
}

function getDocPropsCoreXml() {
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties
  xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  xmlns:dcterms="http://purl.org/dc/terms/"
  xmlns:dcmitype="http://purl.org/dc/dcmitype/"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>Axialy Document</dc:title>
  <dc:creator>Axialy</dc:creator>
  <cp:lastModifiedBy>Axialy</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">2025-02-11T12:00:00Z</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">2025-02-11T12:00:00Z</dcterms:modified>
</cp:coreProperties>
XML;
}

function getDocPropsAppXml() {
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties 
  xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" 
  xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Axialy Admin Generator</Application>
</Properties>
XML;
}
