<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:xs="http://www.w3.org/2001/XMLSchema" xmlns:tei="http://www.tei-c.org/ns/1.0"
    exclude-result-prefixes="xs tei" version="2.0">

    <xsl:strip-space elements="*"/>
    <xsl:output method="xml" encoding="UTF-8" indent="yes"/>


    <xsl:param name="csv-encoding" as="xs:string" select="'utf-8'"/>
    <xsl:param name="csv-uri" as="xs:string" select="'concordance.csv'"/>

    <xsl:variable name="concordance" as="element()*">
        <rows>
            <xsl:choose>
                <xsl:when test="unparsed-text-available($csv-uri, $csv-encoding)">
                    <xsl:variable name="csv" select="unparsed-text($csv-uri, $csv-encoding)"/>
                    <!--Get Header-->
                    <xsl:variable name="header-tokens" as="xs:string*">
                        <xsl:analyze-string select="$csv" regex="\r\n?|\n">
                            <xsl:non-matching-substring>
                                <xsl:if test="position() = 1">
                                    <xsl:copy-of select="tokenize(., ',')"/>
                                </xsl:if>
                            </xsl:non-matching-substring>
                        </xsl:analyze-string>
                    </xsl:variable>
                    <xsl:analyze-string select="$csv" regex="\r\n?|\n">
                        <xsl:non-matching-substring>
                            <xsl:if test="not(position() = 1)">
                                <row>
                                    <xsl:for-each select="tokenize(., ',')">
                                        <xsl:variable name="pos" select="position()"/>
                                        <xsl:element name="{$header-tokens[$pos]}">
                                            <xsl:value-of select="."/>
                                        </xsl:element>
                                    </xsl:for-each>
                                </row>
                            </xsl:if>
                        </xsl:non-matching-substring>
                    </xsl:analyze-string>
                </xsl:when>
                <xsl:otherwise>
                    <xsl:variable name="error">
                        <xsl:text>Error reading "</xsl:text>
                        <xsl:value-of select="$csv-uri"/>
                        <xsl:text>" (encoding "</xsl:text>
                        <xsl:value-of select="$csv-encoding"/>
                        <xsl:text>").</xsl:text>
                    </xsl:variable>
                    <xsl:message>
                        <xsl:value-of select="$error"/>
                    </xsl:message>
                    <xsl:value-of select="$error"/>
                </xsl:otherwise>
            </xsl:choose>
        </rows>
    </xsl:variable>

    <xsl:template match="@* | node()">
        <xsl:copy>
            <xsl:apply-templates select="@* | node()"/>
        </xsl:copy>
    </xsl:template>

    <xsl:template match="tei:term">
        <xsl:element name="term" namespace="http://www.tei-c.org/ns/1.0">
            <xsl:attribute name="ref" select="@ref"/>
            <xsl:attribute name="facs">
                <xsl:variable name="old_file" select="substring-after(@facs, '#')"/>
                <xsl:value-of select="concat('#', $concordance//row[old_file = $old_file]/new_file)"/>
            </xsl:attribute>
            <xsl:value-of select="."/>
        </xsl:element>
    </xsl:template>

    <xsl:template match="tei:facsimile">
        <xsl:variable name="old_file" select="@xml:id"/>
        <xsl:variable name="new_file" select="$concordance//row[old_file = $old_file]/new_file"/>

        <xsl:element name="facsimile" namespace="http://www.tei-c.org/ns/1.0">
            <xsl:attribute name="xml:id" select="$new_file"/>
            <xsl:if test="@style">
                <xsl:attribute name="style" select="@style"/>
            </xsl:if>

            <xsl:apply-templates select="tei:media">
                <xsl:with-param name="old_file" select="$old_file"/>
                <xsl:with-param name="new_file" select="$new_file"/>
            </xsl:apply-templates>
        </xsl:element>
    </xsl:template>

    <xsl:template match="tei:media">
        <xsl:param name="old_file"/>
        <xsl:param name="new_file"/>

        <media xmlns="http://www.tei-c.org/ns/1.0" url="http://images.numismatics.org/archivesimages%2Farchive%2F{$new_file}.jpg" n="{@n}"
            mimeType="image/jpeg" type="IIIFService" height="{@height}" width="{@width}"/>
    </xsl:template>
    
    <xsl:template match="tei:revisionDesc">
        <xsl:element name="revisionDesc" namespace="http://www.tei-c.org/ns/1.0">
            <xsl:apply-templates/>
            <xsl:element name="change" namespace="http://www.tei-c.org/ns/1.0">
                <xsl:attribute name="when" select="substring(xs:string(current-date()), 1, 10)"/>
                <xsl:text>Applied sequential filenames to facsimile images.</xsl:text>
            </xsl:element>
        </xsl:element>
    </xsl:template>

</xsl:stylesheet>
