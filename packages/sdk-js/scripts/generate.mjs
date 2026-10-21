import { readFileSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const spec = JSON.parse(readFileSync(join(root, "openapi.json"), "utf8"));
const output = join(root, "src", "generated.ts");

function typeOf(schema) {
  if (!schema || typeof schema !== "object") {
    return "unknown";
  }
  if (schema.$ref) {
    const name = schema.$ref.split("/").pop();
    return schema.nullable ? `${name} | null` : name;
  }
  let base;
  if (Array.isArray(schema.enum)) {
    base = schema.enum.map((value) => JSON.stringify(value)).join(" | ");
  } else if (schema.type === "array") {
    const item = typeOf(schema.items ?? {});
    base = item.includes(" ") ? `(${item})[]` : `${item}[]`;
  } else if (schema.type === "object") {
    base = schema.properties ? inline(schema) : "Record<string, unknown>";
  } else if (schema.type === "integer" || schema.type === "number") {
    base = "number";
  } else if (schema.type === "boolean") {
    base = "boolean";
  } else if (schema.type === "string") {
    base = "string";
  } else {
    base = "unknown";
  }
  return schema.nullable ? `${base} | null` : base;
}

function inline(schema) {
  const required = new Set(schema.required ?? []);
  const fields = Object.entries(schema.properties).map(([name, prop]) => {
    const optional = required.has(name) ? "" : "?";
    return `${name}${optional}: ${typeOf(prop)}`;
  });
  return `{ ${fields.join("; ")} }`;
}

function interfaceOf(name, schema) {
  if (!schema.properties) {
    return `export type ${name} = ${typeOf(schema)};\n`;
  }
  const required = new Set(schema.required ?? []);
  const fields = Object.entries(schema.properties).map(([prop, propSchema]) => {
    const optional = required.has(prop) ? "" : "?";
    return `    ${prop}${optional}: ${typeOf(propSchema)};`;
  });
  return `export interface ${name} {\n${fields.join("\n")}\n}\n`;
}

const blocks = Object.entries(spec.components.schemas).map(([name, schema]) => interfaceOf(name, schema));
const source = `// Generated from packages/sdk-js/openapi.json. Do not edit.\n\n${blocks.join("\n")}`;

if (process.argv.includes("--check")) {
  const current = readFileSync(output, "utf8");
  if (current !== source) {
    console.error("src/generated.ts does not match openapi.json. Run npm run generate.");
    process.exit(1);
  }
} else {
  writeFileSync(output, source);
}
