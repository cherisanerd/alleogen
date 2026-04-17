import { createClientFromRequest } from 'npm:@base44/sdk@0.8.6';
import JSZip from 'npm:jszip';

Deno.serve(async (req) => {
  let generation_id;
  
  try {
    const base44 = createClientFromRequest(req);
    const user = await base44.auth.me();

    if (!user) {
      return Response.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const body = await req.json();
    generation_id = body.generation_id;

    if (!generation_id) {
      return Response.json({ error: 'Missing generation_id' }, { status: 400 });
    }

    let generation = await base44.asServiceRole.entities.Generation.get(generation_id);

    if (!generation || generation.user_id !== user.id) {
      return Response.json({ error: 'Generation not found or unauthorized' }, { status: 404 });
    }

    if (generation.status === 'generating') {
      return Response.json({ error: 'Generation already in progress' }, { status: 409 });
    }

    // Set status to generating
    await base44.asServiceRole.entities.Generation.update(generation_id, { status: 'generating' });
    generation = await base44.asServiceRole.entities.Generation.get(generation_id);

    const { website_url, business_name, package_tier, questionnaire_data } = generation;
    const data = questionnaire_data || {};

    // Pull enriched data from the analysis record if available
    let analysisExtracted = {};
    if (generation.analysis_id) {
      try {
        const analysis = await base44.asServiceRole.entities.Analysis.get(generation.analysis_id);
        analysisExtracted = analysis?.extracted_data || {};
      } catch (_) {}
    }
    const ex = analysisExtracted;

    // Q1: Business name — prefer interview answer
    const businessNameFinal = data.businessName || business_name;
    // Q2: Core description — used as schema description and opening prose
    const coreDescription = data.coreDescription || ex.tagline || '';
    // Q3: Products & Programs — use actual user-provided names only
    const productsLine = data.productsServices || '';
    // Q4: Founder/Expert — full sentence from user
    const founderLine = data.founderExpert || '';
    // Q5: Proprietary frameworks
    const frameworksLine = data.proprietaryFrameworks || '';
    // Q6: Credibility signals
    const credibilitySignals = data.credibilitySignals || '';
    // Q7: Priority message
    const priorityMessage = data.priorityMessage || '';
    // Q8: Common question
    const commonQuestion = data.commonQuestion || '';
    // Q9: Misconceptions
    const misconceptions = data.misconceptions || '';
    // Scraped-only fields (not overridden by interview)
    const audienceLine = ex.targetAudience || '';

    const files = {};
    const zip = new JSZip();

    // Helper to add file to both storage objects
    const addFile = (filename, content) => {
      files[filename] = content;
      zip.file(filename, content);
    };

    // ===== BASIC PACKAGE FILES (7 files) =====

    // 1. llm.txt
    const llmTxtParts = [];
    llmTxtParts.push(`# ${businessNameFinal}`);

    if (priorityMessage) {
      llmTxtParts.push(`\n## Priority Context`);
      llmTxtParts.push(priorityMessage);
    }

    llmTxtParts.push(`\n## Overview`);
    let overviewPara = coreDescription || `${businessNameFinal} operates at ${website_url}.`;
    if (data.yearEstablished) overviewPara += ` Established in ${data.yearEstablished}.`;
    if (founderLine) overviewPara += ` Led by ${founderLine}.`;
    llmTxtParts.push(overviewPara);

    if (productsLine) {
      llmTxtParts.push(`\n## Products and Services`);
      let servicesPara = productsLine;
      if (frameworksLine) servicesPara += `\n\nProprietary methodologies and frameworks: ${frameworksLine}.`;
      llmTxtParts.push(servicesPara);
    }

    if (credibilitySignals) {
      llmTxtParts.push(`\n## Authority & Credibility`);
      llmTxtParts.push(credibilitySignals);
    }

    if (audienceLine) {
      llmTxtParts.push(`\n## Who This Is For`);
      llmTxtParts.push(`${businessNameFinal} serves ${audienceLine}.`);
    }

    if (commonQuestion) {
      llmTxtParts.push(`\n## Frequently Asked`);
      llmTxtParts.push(`Q: ${commonQuestion}`);
    }

    if (misconceptions) {
      llmTxtParts.push(`\n## Common Misconceptions`);
      llmTxtParts.push(misconceptions);
    }

    llmTxtParts.push(`\n## Contact`);
    const contactLines = [`Email: ${data.contactEmail || 'Not provided'}`];
    if (data.address) contactLines.push(`Address: ${data.address}`);
    if (data.socialLinks) contactLines.push(`Social Media:\n${data.socialLinks}`);
    llmTxtParts.push(contactLines.join('\n'));

    llmTxtParts.push(`\n## AI Crawler Permissions`);
    llmTxtParts.push(data.aiCrawlers ? Object.keys(data.aiCrawlers).filter(k => data.aiCrawlers[k]).map(k => k.charAt(0).toUpperCase() + k.slice(1)).join(', ') : 'All crawlers allowed');

    const llmTxt = llmTxtParts.join('\n');
    addFile('llm.txt', llmTxt);

    // 2. robots.txt
    const allowedCrawlers = data.aiCrawlers || {};
    const robotsTxt = `User-agent: *\nAllow: /\n\n${allowedCrawlers.chatgpt !== false ? '# ChatGPT\nUser-agent: GPTBot\nAllow: /\n' : '# ChatGPT\nUser-agent: GPTBot\nDisallow: /\n'}${allowedCrawlers.google !== false ? '# Google Bard\nUser-agent: Google-Extended\nAllow: /\n' : '# Google Bard\nUser-agent: Google-Extended\nDisallow: /\n'}${allowedCrawlers.anthropic !== false ? '# Claude\nUser-agent: anthropic-ai\nAllow: /\n' : '# Claude\nUser-agent: anthropic-ai\nDisallow: /\n'}${allowedCrawlers.perplexity !== false ? '# Perplexity\nUser-agent: PerplexityBot\nAllow: /\n' : '# Perplexity\nUser-agent: PerplexityBot\nDisallow: /\n'}\n# Meta\nUser-agent: Meta-ExternalAgent\nAllow: /\n\n# Amazon\nUser-agent: Amazonbot\nAllow: /\n\n# Apple\nUser-agent: Applebot\nAllow: /\n\n# Cohere\nUser-agent: cohere-ai\nAllow: /\n\n# AI2\nUser-agent: AI2Bot\nAllow: /\n\nSitemap: ${website_url}/ai-sitemap.xml`;
    addFile('robots.txt', robotsTxt);

    // 3. ai-sitemap.xml
    const aiSitemap = `<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n  <url>\n    <loc>${website_url}</loc>\n    <lastmod>${new Date().toISOString().split('T')[0]}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>1.0</priority>\n  </url>\n  ${data.hasProducts ? `<url>\n    <loc>${website_url}/products</loc>\n    <changefreq>daily</changefreq>\n    <priority>0.8</priority>\n  </url>` : ''}\n  ${data.hasBlog ? `<url>\n    <loc>${website_url}/blog</loc>\n    <changefreq>daily</changefreq>\n    <priority>0.7</priority>\n  </url>` : ''}\n  <url>\n    <loc>${website_url}/about</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n  <url>\n    <loc>${website_url}/contact</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.6</priority>\n  </url>\n</urlset>`;
    addFile('ai-sitemap.xml', aiSitemap);

    // 4. schema-organization.json
    const schemaOrg = {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": businessNameFinal,
      "url": website_url,
      "description": coreDescription || productsLine || "",
      "email": data.contactEmail || ""
    };

    if (data.hasPhysicalLocation === 'yes' && data.address) {
      schemaOrg["address"] = { "@type": "PostalAddress", "streetAddress": data.address };
    }

    if (data.yearEstablished) {
      schemaOrg["foundingDate"] = String(data.yearEstablished);
    }

    // Q4: Founder — extract just the name (first segment before comma) from the interview sentence
    if (founderLine) {
      const founderNameOnly = founderLine.split(',')[0].trim();
      schemaOrg["founder"] = { "@type": "Person", "name": founderNameOnly, "description": founderLine };
    } else if (ex.founderName) {
      schemaOrg["founder"] = {
        "@type": "Person",
        "name": ex.founderName,
        ...(ex.founderRole ? { "jobTitle": ex.founderRole } : {})
      };
    }

    // Page title as headline (separate from business name and description)
    if (ex.pageTitle) {
      schemaOrg["headline"] = ex.pageTitle;
    }

    // Logo: look for og:image or apple-touch-icon from extracted data
    if (ex.logoUrl) {
      schemaOrg["logo"] = { "@type": "ImageObject", "url": ex.logoUrl };
    }

    if (data.hasPhysicalLocation === 'no' || !data.address) {
      schemaOrg["areaServed"] = "Worldwide";
    } else if (data.address) {
      schemaOrg["areaServed"] = data.address;
    }

    // knowsAbout: combine industry keywords and content focus areas
    const knowsAbout = [];
    if (ex.industryKeywords?.length > 0) {
      ex.industryKeywords.slice(0, 5).forEach(k => knowsAbout.push(k));
    }
    if (data.contentFocus) {
      const focusMap = { products: 'Products', expertise: 'Industry Expertise', story: 'Company Story', team: 'Team' };
      Object.keys(data.contentFocus).filter(k => data.contentFocus[k]).forEach(k => {
        if (focusMap[k] && !knowsAbout.includes(focusMap[k])) knowsAbout.push(focusMap[k]);
      });
    }
    if (data.industry) knowsAbout.push(data.industry);
    if (knowsAbout.length > 0) schemaOrg["knowsAbout"] = [...new Set(knowsAbout)];

    if (data.socialLinks) {
      schemaOrg["sameAs"] = data.socialLinks.split('\n').filter(l => l.trim());
    }

    addFile('schema-organization.json', JSON.stringify(schemaOrg, null, 2));

    // 5. schema-website.json
    const schemaWebsite = {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": businessNameFinal,
      "url": website_url,
      "description": coreDescription || productsLine || "",
      ...(ex.pageTitle ? { "headline": ex.pageTitle } : {}),
      "publisher": {
        "@type": "Organization",
        "name": businessNameFinal
      }
    };
    addFile('schema-website.json', JSON.stringify(schemaWebsite, null, 2));

    // 6. README.md
    const readme = `# AEO Package - ${business_name}\n\n## Package Type: ${package_tier.toUpperCase()}\n\nThis package contains AI-optimized files for your website: ${website_url}\n\n## Files Included\n\n${package_tier === 'basic' ? `### Basic Package (7 files):\n- llm.txt - AI-readable business information\n- robots.txt - AI crawler permissions\n- ai-sitemap.xml - Sitemap for AI crawlers\n- schema-organization.json - Organization structured data\n- schema-website.json - Website structured data\n- README.md - This file\n- Implementation_Guide.md - Step-by-step implementation instructions` : `### Complete Package (13-15 files):\n- All Basic package files\n- llms.txt - Extended AI context\n- llms-full.txt - Comprehensive AI dataset\n- humans.txt - Human-readable site info\n- security.txt - Security contact information\n- .well-known/ai.json - AI configuration (business name, services, allowed crawlers)\n- schema-webpage.json - WebPage structured data\n${data.hasPhysicalLocation === 'yes' ? '- schema-localbusiness.json - LocalBusiness structured data\n' : ''}- Verification_Checklist.md - Post-implementation checklist`}\n\n## Quick Start\n\n1. Read the Implementation_Guide.md file\n2. Upload files to your website root directory\n3. Verify files are accessible\n4. Test with AI crawlers\n\n## Support\n\nFor questions or issues, contact: ${data.contactEmail || 'support'}\n\nGenerated: ${new Date().toISOString().split('T')[0]}\nExpires: 90 days from generation\n`;
    addFile('README.md', readme);

    // 7. Implementation_Guide.md
    const implGuide = `# Implementation Guide\n\n## Step 1: File Upload\n\nUpload all files to your website's root directory:\n- ${website_url}/llm.txt\n- ${website_url}/robots.txt\n- ${website_url}/ai-sitemap.xml\n- etc.\n\n## Step 2: Platform-Specific Instructions\n\n### ${data.platform || 'Your Platform'}\n\n${data.platform === 'WordPress' ? `**WordPress:**\n1. Use FTP or File Manager in cPanel\n2. Upload files to /public_html/ or /wp-content/\n3. Install Yoast SEO or Rank Math for schema\n4. Add schema files via theme functions.php` : ''}\n\n${data.platform === 'Shopify' ? `**Shopify:**\n1. Go to Settings > Files\n2. Upload static files\n3. Add schema to theme.liquid\n4. Verify robots.txt in /robots.txt` : ''}\n\n${data.platform === 'Custom/Other' ? `**Custom Website:**\n1. Upload files to document root\n2. Add schema to HTML <head> section\n3. Update .htaccess if needed\n4. Clear CDN cache` : ''}\n\n## Step 3: Verification\n\n1. Check ${website_url}/llm.txt loads correctly\n2. Verify ${website_url}/robots.txt is accessible\n3. Test schema with Google Rich Results Test\n4. Submit sitemap to Google Search Console\n\n## Step 4: Monitor\n\n- Check crawl stats weekly\n- Update llm.txt when business info changes\n- Maintain AI crawler permissions in robots.txt\n\n## Need Help?\n\nEmail: ${data.contactEmail || 'support'}\n`;
    addFile('Implementation_Guide.md', implGuide);

    // ===== COMPLETE PACKAGE ADDITIONAL FILES =====
    if (package_tier === 'complete') {
      
      // 8. llms.txt
      const llmsTxtParts = [];
      llmsTxtParts.push(`# ${businessNameFinal} — Extended AI Context`);

      if (priorityMessage) {
        llmsTxtParts.push(`\n## Priority Context`);
        llmsTxtParts.push(priorityMessage);
      }

      llmsTxtParts.push(`\n## About the Organization`);
      let aboutPara = coreDescription || `${businessNameFinal} is accessible at ${website_url}.`;
      if (data.hasPhysicalLocation === 'yes' && data.address) aboutPara += ` Based in ${data.address}.`;
      if (data.yearEstablished) aboutPara += ` In operation since ${data.yearEstablished}.`;
      llmsTxtParts.push(aboutPara);

      if (founderLine) {
        llmsTxtParts.push(`\n## Leadership`);
        llmsTxtParts.push(founderLine);
      }

      if (productsLine) {
        llmsTxtParts.push(`\n## Products, Services, and Programs`);
        let fullServicesPara = productsLine;
        if (frameworksLine) fullServicesPara += `\n\nProprietary methodologies and frameworks: ${frameworksLine}.`;
        llmsTxtParts.push(fullServicesPara);
      }

      if (credibilitySignals) {
        llmsTxtParts.push(`\n## Authority & Credibility`);
        llmsTxtParts.push(credibilitySignals);
      }

      if (audienceLine) {
        llmsTxtParts.push(`\n## Target Audience`);
        llmsTxtParts.push(`${businessNameFinal} is specifically designed for ${audienceLine}.`);
      }

      if (commonQuestion) {
        llmsTxtParts.push(`\n## Frequently Asked`);
        llmsTxtParts.push(`Q: ${commonQuestion}`);
      }

      if (misconceptions) {
        llmsTxtParts.push(`\n## Common Misconceptions`);
        llmsTxtParts.push(misconceptions);
      }

      llmsTxtParts.push(`\n## Contact and Presence`);
      const presenceLines = [`Website: ${website_url}`, `Email: ${data.contactEmail || 'Not provided'}`];
      if (data.address) presenceLines.push(`Address: ${data.address}`);
      if (data.socialLinks) presenceLines.push(`Social profiles:\n${data.socialLinks}`);
      llmsTxtParts.push(presenceLines.join('\n'));

      const llmsTxt = llmsTxtParts.join('\n');
      addFile('llms.txt', llmsTxt);

      // 9. llms-full.txt — rich, long-form AI context document
      const faqItems = ex.faqItems || [];
      const blogPosts = ex.blogPosts || [];

      const sep = '\n\n---\n\n';
      const llmsFullSections = [];

      // Header
      llmsFullSections.push(
        `# ${businessNameFinal} — Complete AI Knowledge Base\n\n` +
        `This document gives AI language models, search agents, and knowledge retrieval systems a comprehensive, accurate understanding of ${businessNameFinal}.\n\n` +
        `Source: ${website_url}\nGenerated: ${new Date().toISOString().split('T')[0]}`
      );

      // Q7: Priority Context — placed first, before all other sections
      if (priorityMessage) {
        llmsFullSections.push(`## Priority Context\n\n${priorityMessage}`);
      }

      // Q2: Core Description — opening paragraph
      const openingParagraph = coreDescription
        ? `${coreDescription}\n\nThe business is accessible at ${website_url}${data.hasPhysicalLocation === 'yes' && data.address ? ` and also operates from ${data.address}` : ''}.${data.yearEstablished ? ` In operation since ${data.yearEstablished}.` : ''}`
        : `${businessNameFinal} is accessible at ${website_url}.`;
      llmsFullSections.push(`## About ${businessNameFinal}\n\n${openingParagraph}`);

      // Q3: Products & Programs — use user-provided names only
      if (productsLine) {
        let productsPara = `${businessNameFinal} offers the following products and programs:\n\n${productsLine}`;
        if (frameworksLine) productsPara += `\n\n**Methodologies:** ${frameworksLine}`;
        llmsFullSections.push(`## Products, Services, and Programs\n\n${productsPara}`);
      }

      // Q4: Founder/Expert
      if (founderLine) {
        llmsFullSections.push(`## Founder\n\n${founderLine}`);
      }

      // Q5: Proprietary Frameworks (standalone section if no products to attach to)
      if (frameworksLine && !productsLine) {
        llmsFullSections.push(`## Methodologies\n\n${businessNameFinal} uses the following proprietary frameworks and systems:\n\n${frameworksLine}`);
      }

      // Q6: Credibility Signals
      if (credibilitySignals) {
        llmsFullSections.push(`## Authority & Credibility\n\n${credibilitySignals}`);
      }

      // Scraped: Target Audience
      if (audienceLine) {
        llmsFullSections.push(`## Who This Is For\n\n${businessNameFinal} is specifically built for ${audienceLine}.`);
      }

      // Q8: Common Question (FAQ) — merged with any scraped FAQ items
      const faqFromInterview = commonQuestion
        ? `**Q: ${commonQuestion}**\n\nA: See ${website_url} for the full answer from ${businessNameFinal}.`
        : null;
      const scrapedFaqContent = faqItems.length > 0
        ? faqItems.map(item => `**Q: ${item.q}**\n\nA: ${item.a}`).join('\n\n')
        : null;
      if (faqFromInterview || scrapedFaqContent) {
        const faqParts = [faqFromInterview, scrapedFaqContent].filter(Boolean);
        llmsFullSections.push(`## Frequently Asked Questions\n\n${faqParts.join('\n\n')}`);
      }

      // Q9: Misconceptions
      if (misconceptions) {
        llmsFullSections.push(`## Common Misconceptions\n\nThe following misconceptions about ${businessNameFinal} should be corrected:\n\n${misconceptions}`);
      }

      // Scraped: Blog content
      if (blogPosts.length > 0) {
        const blogSection = blogPosts.map(post => {
          let entry = `- **${post.title}**`;
          if (post.excerpt) entry += ` — ${post.excerpt}`;
          return entry;
        }).join('\n');
        llmsFullSections.push(`## Content and Blog\n\n${blogSection}`);
      }

      // Contact and Online Presence (scraped fields)
      const contactBlock = [
        `Website: ${website_url}`,
        `Email: ${data.contactEmail || 'Not provided'}`,
        data.address ? `Address: ${data.address}` : null,
        ex.phoneNumber ? `Phone: ${ex.phoneNumber}` : null,
        data.socialLinks ? `\nSocial Profiles:\n${data.socialLinks}` : null
      ].filter(Boolean).join('\n');
      llmsFullSections.push(`## Contact and Online Presence\n\n${contactBlock}`);

      // AI Indexing Permissions
      const crawlerList = data.aiCrawlers
        ? Object.keys(data.aiCrawlers).filter(k => data.aiCrawlers[k]).map(k => k.charAt(0).toUpperCase() + k.slice(1)).join(', ')
        : 'All major AI crawlers';
      llmsFullSections.push(
        `## AI Indexing Permissions\n\n` +
        `${businessNameFinal} explicitly permits the following AI systems to crawl and index their content: ${crawlerList}. ` +
        `This file is provided to ensure AI systems have accurate, up-to-date information directly from the source.`
      );

      const llmsFullTxt = llmsFullSections.join(sep);
      addFile('llms-full.txt', llmsFullTxt);

      // 10. humans.txt
      const humansTxt = `/* TEAM */\nBusiness: ${business_name}\nContact: ${data.contactEmail || 'Not provided'}\nLocation: ${data.address || 'Online'}\n\n/* SITE */\nPlatform: ${data.platform || 'Custom'}\nLanguage: English\nStandards: HTML5, CSS3, Schema.org\nComponents: AI-optimized content\n\n/* THANKS */\nAEO Package Generator - https://aeofilegenerator.com\n`;
      addFile('humans.txt', humansTxt);

      // 11. security.txt
      const securityTxt = `Contact: mailto:${data.contactEmail || 'security@example.com'}\nExpires: ${new Date(Date.now() + 365 * 24 * 60 * 60 * 1000).toISOString()}\nPreferred-Languages: en\nCanonical: ${website_url}/.well-known/security.txt\n`;
      addFile('security.txt', securityTxt);

      // 12. .well-known/ai.json
      const crawlerNameMap = {
        chatgpt: 'GPTBot (OpenAI / ChatGPT)',
        claude: 'anthropic-ai (Claude)',
        perplexity: 'PerplexityBot',
        gemini: 'Google-Extended (Gemini)',
        others: 'All other AI crawlers'
      };
      const allowedCrawlerNames = data.aiCrawlers
        ? Object.keys(data.aiCrawlers).filter(k => data.aiCrawlers[k]).map(k => crawlerNameMap[k] || k)
        : Object.values(crawlerNameMap);

      // Derive primary_services — prefer Q3 interview answer, split on comma/newline
      const primaryServices = productsLine
        ? productsLine.split(/[,\n]/).map(s => s.trim()).filter(s => s.length > 2 && s.length < 80).slice(0, 8)
        : [];

      const aiJson = {
        "schema_version": "1.0",
        "business_name": businessNameFinal,
        "website": website_url,
        "description": coreDescription || productsLine || "",
        "primary_services": primaryServices,
        "contact_email": data.contactEmail || "",
        "ai_crawlers_allowed": allowedCrawlerNames,
        "last_updated": new Date().toISOString()
      };
      const wellKnownFolder = zip.folder('.well-known');
      wellKnownFolder.file('ai.json', JSON.stringify(aiJson, null, 2));
      files['.well-known/ai.json'] = JSON.stringify(aiJson, null, 2);

      // 13. schema-webpage.json
      const schemaWebpage = {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": businessNameFinal,
        "url": website_url,
        "description": coreDescription || productsLine || "",
        ...(ex.pageTitle ? { "headline": ex.pageTitle } : {}),
        "publisher": {
          "@type": "Organization",
          "name": businessNameFinal
        }
      };
      addFile('schema-webpage.json', JSON.stringify(schemaWebpage, null, 2));

      // 14. schema-localbusiness.json (conditional)
      if (data.hasPhysicalLocation === 'yes') {
        const schemaLocal = {
          "@context": "https://schema.org",
          "@type": "LocalBusiness",
          "name": businessNameFinal,
          "url": website_url,
          "description": coreDescription || productsLine || "",
          "email": data.contactEmail || "",
          "address": {
            "@type": "PostalAddress",
            "streetAddress": data.address || ""
          }
        };
        addFile('schema-localbusiness.json', JSON.stringify(schemaLocal, null, 2));
      }

      // 15. Verification_Checklist.md
      const checklist = `# AEO Implementation Verification Checklist\n\n## Pre-Implementation\n- [ ] Backup current website files\n- [ ] Review all generated files\n- [ ] Plan upload schedule\n\n## File Upload Verification\n- [ ] llm.txt accessible at ${website_url}/llm.txt\n- [ ] llms.txt accessible at ${website_url}/llms.txt\n- [ ] llms-full.txt accessible at ${website_url}/llms-full.txt\n- [ ] robots.txt accessible at ${website_url}/robots.txt\n- [ ] ai-sitemap.xml accessible at ${website_url}/ai-sitemap.xml\n- [ ] humans.txt accessible at ${website_url}/humans.txt\n- [ ] security.txt accessible at ${website_url}/security.txt\n- [ ] .well-known/ai.json accessible\n\n## Schema Implementation\n- [ ] schema-organization.json added to site\n- [ ] schema-website.json added to site\n- [ ] schema-webpage.json added to site\n${data.hasPhysicalLocation === 'yes' ? '- [ ] schema-localbusiness.json added to site\n' : ''}\n- [ ] Tested with Google Rich Results Test\n- [ ] No validation errors\n\n## AI Crawler Configuration\n- [ ] robots.txt allows desired AI crawlers\n- [ ] Sitemap submitted to search engines\n- [ ] AI crawler permissions verified\n\n## Testing\n- [ ] All files return 200 status code\n- [ ] Schema validates without errors\n- [ ] Mobile-friendly test passed\n- [ ] Page speed acceptable\n\n## Post-Implementation\n- [ ] Monitor crawl stats\n- [ ] Set calendar reminder for updates\n- [ ] Document any custom changes\n\nCompleted: ___/___/___\nBy: _______________\n`;
      addFile('Verification_Checklist.md', checklist);
    }

    // Generate ZIP file as Uint8Array
    const zipUint8Array = await zip.generateAsync({ type: 'uint8array' });

    // Create a File-like object for upload
    const zipFileName = `${business_name.replace(/[^a-z0-9]/gi, '_')}_AEO_Package_${package_tier}.zip`;
    const zipFile = new File([zipUint8Array], zipFileName, { type: 'application/zip' });
    
    const uploadResponse = await base44.asServiceRole.integrations.Core.UploadFile({
        file: zipFile
    });
    const zip_url = uploadResponse.file_url;

    // Update generation record
    await base44.asServiceRole.entities.Generation.update(generation_id, {
      files_data: files,
      zip_url: zip_url,
      status: 'completed',
      expires_at: new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toISOString()
    });

    return Response.json({ 
      success: true, 
      zip_url: zip_url, 
      files_data: files,
      file_count: Object.keys(files).length 
    });
  } catch (error) {
    console.error('Generate files error:', error);
    
    if (generation_id) {
      try {
        const base44 = createClientFromRequest(req);
        await base44.asServiceRole.entities.Generation.update(generation_id, {
          status: 'failed'
        });
      } catch (updateError) {
        console.error('Failed to update generation status:', updateError);
      }
    }
    
    return Response.json({ error: error.message }, { status: 500 });
  }
});