#!/bin/bash
# Build the release package for the PKP Plugin Gallery and its gallery XML entry.
#
#   dev/package.sh [--ref <git ref>] [--out <dir>] [--tag <tag>]
#
# The package is `git archive` of the ref (default HEAD), without the files marked
# export-ignore in .gitattributes, in a single folder editorAssignments/.
# The version comes from version.xml; --tag (e.g. v1.0.1) must match it.
#
# Output in <dir> (default dist/):
#   editorAssignments-v<version>.tar.gz          the package
#   editorAssignments-v<version>.tar.gz.md5      its md5 sum
#   gallery-release.xml                       <release> element to add to plugins.xml
#   gallery-plugin.xml                        complete <plugin> entry (first submission)
#
# Environment: RELEASE_DESCRIPTION (release notes for the gallery, one line),
#   GITHUB_REPOSITORY (owner/repo).
set -euo pipefail

PRODUCT=editorAssignments
REPO="${GITHUB_REPOSITORY:-cboulanger/ojs-editor-assignments}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REF=HEAD
OUT="$ROOT/dist"
TAG=""
while [ $# -gt 0 ]; do
	case "$1" in
		--ref) REF="$2"; shift ;;
		--out) OUT="$2"; shift ;;
		--tag) TAG="$2"; shift ;;
		-h|--help) sed -n '2,19p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
		*) echo "Unknown argument: $1" >&2; exit 2 ;;
	esac
	shift
done
cd "$ROOT"

# Version from version.xml at the ref: 1.0.1.0 -> v1.0.1, 1.0.1.2 -> v1.0.1-2
VERSION_XML="$(git show "$REF:version.xml")"
RELEASE="$(sed -n 's:.*<release>\(.*\)</release>.*:\1:p' <<<"$VERSION_XML")"
DATE="$(sed -n 's:.*<date>\(.*\)</date>.*:\1:p' <<<"$VERSION_XML")"
if ! [[ "$RELEASE" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)\.([0-9]+)$ ]]; then
	echo "version.xml: release '$RELEASE' is not four numbers" >&2; exit 1
fi
VERSION_TAG="v${BASH_REMATCH[1]}.${BASH_REMATCH[2]}.${BASH_REMATCH[3]}"
[ "${BASH_REMATCH[4]}" != 0 ] && VERSION_TAG="$VERSION_TAG-${BASH_REMATCH[4]}"
if [ -n "$TAG" ] && [ "$TAG" != "$VERSION_TAG" ]; then
	echo "Tag $TAG does not match version.xml (release $RELEASE, expected tag $VERSION_TAG)" >&2; exit 1
fi
if ! [[ "$DATE" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
	echo "version.xml: date '$DATE' is not YYYY-MM-DD" >&2; exit 1
fi

mkdir -p "$OUT"
FILE="$PRODUCT-$VERSION_TAG.tar.gz"
git archive --format=tar.gz --prefix="$PRODUCT/" -o "$OUT/$FILE" "$REF"

# The package: one folder named after the product, with what OJS needs and nothing else
LIST="$(tar tzf "$OUT/$FILE")"
if grep -qv "^$PRODUCT/" <<<"$LIST"; then
	echo "Package has files outside $PRODUCT/" >&2; exit 1
fi
for required in version.xml EditorAssignmentsPlugin.php LICENSE locale/en/locale.po locale/de/locale.po pages/EditorAssignmentsHandler.php classes/Assignments.php mailables/EditorMessage.php js/editorAssignments.js css/editorAssignments.css templates/index.tpl templates/submissions.tpl templates/compose.tpl templates/transfer.tpl; do
	grep -qx "$PRODUCT/$required" <<<"$LIST" || { echo "Package lacks $required" >&2; exit 1; }
done
for excluded in dev/ .github/; do
	if grep -q "^$PRODUCT/$excluded" <<<"$LIST"; then echo "Package contains $excluded" >&2; exit 1; fi
done
if grep -qE '(^|/)(node_modules|package-lock\.json|package\.json|\.DS_Store)$|/node_modules/' <<<"$LIST"; then
	echo "Package contains npm or system files" >&2; exit 1
fi

if command -v md5sum >/dev/null; then
	MD5="$(md5sum "$OUT/$FILE" | cut -d' ' -f1)"
else
	MD5="$(md5 -q "$OUT/$FILE")"
fi
echo "$MD5  $FILE" > "$OUT/$FILE.md5"

# Gallery XML (see https://github.com/pkp/plugin-gallery, plugins.xsd)
PACKAGE_URL="https://github.com/$REPO/releases/download/$VERSION_TAG/$FILE" \
RELEASE="$RELEASE" DATE="$DATE" MD5="$MD5" OUT="$OUT" ROOT="$ROOT" \
RELEASE_DESCRIPTION="${RELEASE_DESCRIPTION:-Editor Assignments $VERSION_TAG for OJS 3.5.}" \
python3 - <<'EOF'
import os
from xml.sax.saxutils import escape
e = os.environ
release = f'''		<release date="{e["DATE"]}" version="{e["RELEASE"]}" md5="{e["MD5"]}">
			<package>{escape(e["PACKAGE_URL"])}</package>
			<compatibility application="ojs2">
				<version>~3.5.0.0</version>
			</compatibility>
			<description locale="en">{escape(e["RELEASE_DESCRIPTION"])}</description>
			<description locale="en_US">{escape(e["RELEASE_DESCRIPTION"])}</description>
		</release>'''
dev = os.path.join(e["ROOT"], "dev", "gallery")
plugin = open(os.path.join(dev, "plugin.xml"), encoding="utf-8").read()
description = open(os.path.join(dev, "description.html"), encoding="utf-8").read().strip()
plugin = (plugin.replace("@DESCRIPTION@", description)
          .replace("@RELEASES@", release))
open(os.path.join(e["OUT"], "gallery-release.xml"), "w", encoding="utf-8").write(release + "\n")
open(os.path.join(e["OUT"], "gallery-plugin.xml"), "w", encoding="utf-8").write(plugin)
EOF

echo "Built $OUT/$FILE (md5 $MD5, $(wc -l <<<"$LIST" | tr -d ' ') entries)"
