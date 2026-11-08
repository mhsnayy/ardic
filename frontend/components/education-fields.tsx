import type { Education, EducationType } from "@/lib/api/types";
import { Field, SelectField, TextareaField } from "@/components/forms";

export function EducationFields({ types, e }: { types: EducationType[]; e?: Education }) {
  return (
    <>
      <Field name="name" label="Eğitim adı" defaultValue={e?.name} />
      <SelectField
        name="type"
        label="Tür"
        placeholder="Tür seçin"
        options={types.map((t) => ({ value: t.value, label: t.label }))}
        defaultValue={e?.type}
      />
      <TextareaField name="description" label="Açıklama" defaultValue={e?.description ?? ""} />
    </>
  );
}
