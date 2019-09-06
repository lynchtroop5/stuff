<?xml version="1.0"?>
<!--****************************************************************************
 * Copyright 2011 CommSys Incorporated, Dayton, OH USA. 
 * All rights reserved. 
 *
 * Federal copyright law prohibits unauthorized reproduction by any means 
 * and imposes fines up to $25,000 for violation. 
 *
 * CommSys Incorporated makes no representations about the suitability of
 * this software for any purpose. No express or implied warranty is provided
 * unless under a souce code license agreement.
  ****************************************************************************-->
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

<xsl:param name="filter"  />
<xsl:param name="relatedValue" />

<xsl:param name="search" select="'code'" />
<xsl:param name="stripBeforeSlash" select="0" />

<xsl:output method="html" />

<xsl:template match="/">
	<!-- handle processing with a related value. -->
    <xsl:if test="$relatedValue != ''">
        <xsl:if test="$search = 'code'">
            <xsl:for-each select="/codes/c[starts-with(translate(v,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),translate(concat($relatedValue,'-',$filter),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'))]">
				<xsl:element name="a">
                    <xsl:attribute name="class">clips-dd-value</xsl:attribute>
                    <xsl:attribute name="href">#</xsl:attribute>
                    <xsl:choose>
                        <xsl:when test="substring-after(v,'-') = ''">
                            <span><xsl:text disable-output-escaping="yes"><![CDATA[&nbsp;]]></xsl:text></span>
                        </xsl:when>
                        <xsl:otherwise>
                            <span><xsl:value-of select="substring-after(v,'-')" /></span>
                        </xsl:otherwise>
                    </xsl:choose>
                    <span><xsl:value-of select="d" /></span>
				</xsl:element>
			</xsl:for-each>
		</xsl:if>

		<xsl:if test="$search = 'description'">
			<xsl:for-each select="/codes/c[starts-with(translate(v,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),translate(concat($relatedValue,'-'),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')) and contains(translate(d,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),translate($filter,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'))]">
				<xsl:element name="a">
                    <xsl:attribute name="class">clips-dd-value</xsl:attribute>
                    <xsl:attribute name="href">#</xsl:attribute>
                    <xsl:choose>
                        <xsl:when test="substring-after(v,'-') = ''">
                            <span><xsl:text disable-output-escaping="yes"><![CDATA[&nbsp;]]></xsl:text></span>
                        </xsl:when>
                        <xsl:otherwise>
                            <span><xsl:value-of select="substring-after(v,'-')" /></span>
                        </xsl:otherwise>
                    </xsl:choose>
                    <span><xsl:value-of select="d" /></span>
				</xsl:element>
			</xsl:for-each>
		</xsl:if>
	</xsl:if>
    
	<xsl:if test="$relatedValue = ''">
		<xsl:if test="$search = 'code'">
			<xsl:if test="$stripBeforeSlash = 1">
				<xsl:for-each select="/codes/c[starts-with(translate(substring-after(v,'-'),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),translate($filter,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'))]">
                    <xsl:sort select="substring-after(v,'-')" />
					<xsl:element name="a">
                        <xsl:attribute name="class">clips-dd-value</xsl:attribute>
                        <xsl:attribute name="href">#</xsl:attribute>
                        <xsl:choose>
                            <xsl:when test="substring-after(v,'-') = ''">
                                <span><xsl:text disable-output-escaping="yes"><![CDATA[&nbsp;]]></xsl:text></span>
                            </xsl:when>
                            <xsl:otherwise>
                                <span><xsl:value-of select="substring-after(v,'-')" /></span>
                            </xsl:otherwise>
                        </xsl:choose>
                        <span><xsl:value-of select="d" /></span>
					</xsl:element>
				</xsl:for-each>
			</xsl:if>

			<xsl:if test="$stripBeforeSlash = 0">
				<xsl:for-each select="/codes/c[starts-with(translate(v,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),translate($filter,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'))]">
					<xsl:element name="a">
                        <xsl:attribute name="class">clips-dd-value</xsl:attribute>
                        <xsl:attribute name="href">#</xsl:attribute>
                        <xsl:choose>
                            <xsl:when test="v = ''">
                                <span><xsl:text disable-output-escaping="yes"><![CDATA[&nbsp;]]></xsl:text></span>
                            </xsl:when>
                            <xsl:otherwise>
                                <span><xsl:value-of select="v" /></span>
                            </xsl:otherwise>
                        </xsl:choose>
                        <span><xsl:value-of select="d" /></span>
					</xsl:element>
				</xsl:for-each>
			</xsl:if>
		</xsl:if>

		<xsl:if test="$search = 'description'">
            <xsl:if test="$stripBeforeSlash = 1">
                <xsl:for-each select="/codes/c[contains(translate(d,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),translate($filter,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'))]|/codes/c[starts-with(translate(substring-after(v,'-'),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),translate($filter,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'))]">
                    <xsl:sort select="substring-after(v,'-')" />
                    <xsl:element name="a">
                        <xsl:attribute name="class">clips-dd-value</xsl:attribute>
                        <xsl:attribute name="href">#</xsl:attribute>
                        <xsl:choose>
                            <xsl:when test="substring-after(v,'-') = ''">
                                <span><xsl:text disable-output-escaping="yes"><![CDATA[&nbsp;]]></xsl:text></span>
                            </xsl:when>
                            <xsl:otherwise>
                                <span><xsl:value-of select="substring-after(v,'-')" /></span>
                            </xsl:otherwise>
                        </xsl:choose>
                        <span><xsl:value-of select="d" /></span>
                    </xsl:element>
                </xsl:for-each>
            </xsl:if>

            <xsl:if test="$stripBeforeSlash = 0">
                <xsl:for-each select="/codes/c[contains(translate(d,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),translate($filter,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'))]|/codes/c[starts-with(translate(v,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),translate($filter,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'))]">
                    <xsl:element name="a">
                        <xsl:attribute name="class">clips-dd-value</xsl:attribute>
                        <xsl:attribute name="href">#</xsl:attribute>
                        <xsl:choose>
                            <xsl:when test="v = ''">
                                <span><xsl:text disable-output-escaping="yes"><![CDATA[&nbsp;]]></xsl:text></span>
                            </xsl:when>
                            <xsl:otherwise>
                                <span><xsl:value-of select="v" /></span>
                            </xsl:otherwise>
                        </xsl:choose>
                        <span><xsl:value-of select="d" /></span>
                    </xsl:element>
                </xsl:for-each>
            </xsl:if>
        </xsl:if>
	</xsl:if>
</xsl:template>

</xsl:stylesheet>
