import { useEffect, useState } from "react";
import { fetchUsers } from "@/api/endpoint/users.api";
import { useAppSelector } from "@/hooks/useAppDispatch";
import { User } from "@/models/index";

export function useAssignableUsers(): User[]{
    const me = useAppSelector(state => state.auth.user);
    const [users, setUsers] = useState<User[]>(me ? [me]: []);

    useEffect(() => {
        const fallbackUsers = me ?[me]:[];
        fetchUsers()
            .then(list => {
                const users = list.length >0 ? list : fallbackUsers;
                setUsers(users)
            })
            .catch(() => setUsers(fallbackUsers));
    }, [me]);

    return users;
}