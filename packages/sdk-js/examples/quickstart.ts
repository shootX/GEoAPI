import { GeoAPI } from "../src/index.js";

const api = new GeoAPI({ apiKey: process.env.GEOAPI_API_KEY! });
const brands = await api.brands.list({ limit: 20 });
const models = await api.models.byBrand("bmw");
const generations = await api.generations.byModel("314");
const modifications = await api.modifications.list({ model_id: "314", year_from: 2020 });
const detail = await api.modifications.get("12345");

export { brands, models, generations, modifications, detail };
